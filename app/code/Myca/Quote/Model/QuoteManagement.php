<?php

declare(strict_types=1);

namespace Myca\Quote\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote as Cart;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Myca\Quote\Model\ResourceModel\Quote as QuoteResource;

/**
 * Seul point d'écriture des devis. Règles :
 * - la demande reprend les articles et options du panier du client ;
 * - la proposition fixe les prix TTC et la validité, et fige un PDF versionné (empreinte sha256) ;
 * - l'acceptation ne crée ni commande ni facture ;
 * - « Transformer en commande » remet les articles au panier aux prix convenus, avant la date de validité ;
 *   la commande et la facture suivent le parcours normal de paiement.
 */
class QuoteManagement
{
    public const PDF_DIR = 'myca_quote';

    public function __construct(
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteResource $quoteResource,
        private readonly QuoteStatus $quoteStatus,
        private readonly QuoteCalculator $calculator,
        private readonly PdfRenderer $pdfRenderer,
        private readonly Filesystem $filesystem,
        private readonly Json $json,
        private readonly TimezoneInterface $timezone,
        private readonly TaxCalculationInterface $taxCalculation,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function createFromCart(Cart $cart, int $customerId, string $note, bool $send, string $author): Quote
    {
        $items = [];
        foreach ($cart->getAllVisibleItems() as $cartItem) {
            $items[] = [
                'product_id' => (int) $cartItem->getData('product_id'),
                'sku' => (string) $cartItem->getSku(),
                'name' => (string) $cartItem->getName(),
                'buy_request' => (string) $this->json->serialize($cartItem->getBuyRequest()->getData()),
                'qty' => (float) $cartItem->getQty(),
                'catalog_price' => (float) $cartItem->getPriceInclTax(),
            ];
        }
        if (!$items) {
            throw new LocalizedException(__('Ajoutez au panier les articles et quantités souhaités, puis demandez votre devis.'));
        }
        $note = trim($note);
        if (mb_strlen($note) > 2000) {
            throw new LocalizedException(__('Votre message ne doit pas dépasser 2 000 caractères.'));
        }
        $status = $send ? QuoteStatus::REQUESTED : QuoteStatus::DRAFT;
        $connection = $this->quoteResource->connection();
        $connection->beginTransaction();
        try {
            $quote = $this->quoteFactory->create();
            $quote->setData([
                'customer_id' => $customerId,
                'store_id' => (int) $cart->getStoreId(),
                'status' => $status,
                'customer_note' => $note === '' ? null : $note,
            ]);
            $this->quoteResource->save($quote);
            $quote->setData('increment_id', sprintf('DEV-%s-%04d', date('Y'), (int) $quote->getId()));
            $this->quoteResource->save($quote);
            $this->quoteResource->insertItems((int) $quote->getId(), $items);
            $this->quoteResource->addHistory((int) $quote->getId(), $status, $author, $note === '' ? null : $note);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $quote;
    }

    /**
     * Action du client ou de MYCA sur le statut (envoi d'un brouillon, acceptation, refus, annulation).
     *
     * @throws LocalizedException
     */
    public function changeStatus(Quote $quote, string $status, string $actor, string $author, ?string $message = null): void
    {
        $connection = $this->quoteResource->connection();
        $connection->beginTransaction();
        $expiredOn = null;
        try {
            $row = $this->quoteResource->lockRow((int) $quote->getId());
            if ($status === QuoteStatus::ACCEPTED && $this->isExpired($row)) {
                $this->expire($quote);
                $expiredOn = (string) $row['valid_until'];
            } elseif (!$this->quoteStatus->canTransition((string) ($row['status'] ?? ''), $status, $actor) || $status === QuoteStatus::PROPOSED) {
                throw new LocalizedException(__('Cette action n’est plus possible sur le devis %1.', $quote->getIncrementId()));
            } else {
                $quote->setData('status', $status);
                $this->quoteResource->save($quote);
                $message = trim((string) $message);
                $this->quoteResource->addHistory((int) $quote->getId(), $status, $author, $message === '' ? null : $message);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        if ($expiredOn !== null) {
            throw new LocalizedException(__('Ce devis a expiré le %1 : demandez-en un nouveau.', $this->formatDate($expiredOn)));
        }
    }

    /**
     * Proposition MYCA : prix TTC par ligne, validité, conditions. Fige une nouvelle version du PDF.
     *
     * @param array<int, float|string> $prices prix unitaire TTC par item_id
     * @throws LocalizedException
     */
    public function propose(Quote $quote, array $prices, string $validUntil, string $note, string $author): void
    {
        $today = $this->today((int) $quote->getData('store_id'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil) || $validUntil < $today) {
            throw new LocalizedException(__('Indiquez une date de validité à partir d’aujourd’hui.'));
        }
        $items = $this->quoteResource->getItems((int) $quote->getId());
        $lines = [];
        foreach ($items as $item) {
            $price = str_replace(',', '.', trim((string) ($prices[(int) $item['item_id']] ?? '')));
            if (!is_numeric($price) || (float) $price <= 0) {
                throw new LocalizedException(__('Indiquez un prix unitaire TTC pour « %1 ».', $item['name']));
            }
            $lines[(int) $item['item_id']] = [
                'name' => (string) $item['name'],
                'sku' => (string) $item['sku'],
                'qty' => (float) $item['qty'],
                'unit_price_incl_tax' => round((float) $price, 2),
                'tax_percent' => $this->getTaxPercent((int) $item['product_id'], $quote),
            ];
        }
        $totals = $this->calculator->totals($lines);

        $connection = $this->quoteResource->connection();
        $connection->beginTransaction();
        try {
            $row = $this->quoteResource->lockRow((int) $quote->getId());
            if (!$this->quoteStatus->canTransition((string) ($row['status'] ?? ''), QuoteStatus::PROPOSED, QuoteStatus::ACTOR_ADMIN)) {
                throw new LocalizedException(__('Ce devis ne peut plus recevoir de proposition.'));
            }
            $version = (int) $row['version'] + 1;
            foreach ($lines as $itemId => $line) {
                $this->quoteResource->updateItem((int) $quote->getId(), $itemId, [
                    'unit_price_incl_tax' => $line['unit_price_incl_tax'],
                    'tax_percent' => $line['tax_percent'],
                ]);
            }
            $customer = $this->customerRepository->getById($quote->getCustomerId());
            $pdf = $this->pdfRenderer->render([
                'increment_id' => $quote->getIncrementId(),
                'version' => $version,
                'date' => $this->formatDate($today, (int) $quote->getData('store_id')),
                'valid_until' => $this->formatDate($validUntil, (int) $quote->getData('store_id')),
                'customer' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                'email' => (string) $customer->getEmail(),
                'note' => trim($note),
                'totals' => $totals,
            ], array_values($lines));
            $path = sprintf('%s/%s-v%d.pdf', self::PDF_DIR, $quote->getIncrementId(), $version);
            $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->writeFile($path, $pdf);

            $quote->addData($totals + [
                'status' => QuoteStatus::PROPOSED,
                'valid_until' => $validUntil,
                'proposal_note' => trim($note) === '' ? null : trim($note),
                'version' => $version,
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $pdf),
            ]);
            $this->quoteResource->save($quote);
            $this->quoteResource->addHistory(
                (int) $quote->getId(),
                QuoteStatus::PROPOSED,
                $author,
                (string) __('Proposition version %1, valable jusqu’au %2.', $version, $this->formatDate($validUntil, (int) $quote->getData('store_id'))) . (trim($note) === '' ? '' : "\n" . trim($note))
            );
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * PDF figé de la dernière proposition. Refusé si le fichier ne correspond plus à son empreinte.
     *
     * @throws LocalizedException
     */
    public function getPdf(Quote $quote): string
    {
        $path = (string) $quote->getData('pdf_path');
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        if ($path === '' || !$directory->isFile($path)) {
            throw new LocalizedException(__('Le PDF de ce devis n’est pas disponible.'));
        }
        $content = $directory->readFile($path);
        if (!hash_equals((string) $quote->getData('pdf_sha256'), hash('sha256', $content))) {
            throw new LocalizedException(__('Le PDF de ce devis a été altéré : contactez MYCA.'));
        }

        return $content;
    }

    /**
     * Remplace le contenu du panier du client par les articles du devis accepté, aux prix convenus.
     *
     * @throws LocalizedException
     */
    public function convertToCart(Quote $quote, Cart $cart): void
    {
        $row = $this->quoteResource->lockRow((int) $quote->getId());
        if ($this->isExpired($row)) {
            $this->expire($quote);
            throw new LocalizedException(__('Ce devis a expiré : demandez-en un nouveau.'));
        }
        if (($row['status'] ?? '') !== QuoteStatus::ACCEPTED) {
            throw new LocalizedException(__('Seul un devis accepté peut être transformé en commande.'));
        }
        foreach ($cart->getAllVisibleItems() as $cartItem) {
            $cart->removeItem((int) $cartItem->getId());
        }
        foreach ($this->quoteResource->getItems((int) $quote->getId()) as $item) {
            $product = $this->productRepository->getById((int) $item['product_id'], false, (int) $quote->getData('store_id'), true);
            if (!$product instanceof Product) {
                throw new LocalizedException(__('« %1 » n’est plus disponible.', $item['name']));
            }
            $request = new DataObject((array) $this->json->unserialize((string) $item['buy_request']));
            $request->setData('qty', (float) $item['qty']);
            $result = $cart->addProduct($product, $request);
            if (is_string($result)) {
                throw new LocalizedException(__('« %1 » : %2', $item['name'], $result));
            }
        }
        $cart->setData('myca_quote_id', (int) $quote->getId());
        $this->cartRepository->save($cart);
        // Les adresses du panier de session gardent en mémoire l'ancienne liste d'articles : on recalcule les totaux
        // sur un panier relu.
        $fresh = $this->cartRepository->get((int) $cart->getId());
        if ($fresh instanceof Cart) {
            $fresh->setData('totals_collected_flag', false);
            $fresh->collectTotals();
            $this->cartRepository->save($fresh);
        }
        $this->quoteResource->addHistory((int) $quote->getId(), QuoteStatus::ACCEPTED, (string) __('Client'), (string) __('Articles remis au panier aux prix du devis.'));
    }

    /**
     * Commande passée depuis un panier issu d'un devis : le devis est « commandé », une seule fois.
     */
    public function markOrdered(int $quoteId, int $orderId, string $orderIncrementId): void
    {
        $quote = $this->load($quoteId);
        if ($quote === null || $quote->getStatus() !== QuoteStatus::ACCEPTED) {
            return;
        }
        $quote->addData(['status' => QuoteStatus::ORDERED, 'order_id' => $orderId, 'order_increment_id' => $orderIncrementId]);
        $this->quoteResource->save($quote);
        $this->quoteResource->addHistory($quoteId, QuoteStatus::ORDERED, (string) __('Système'), (string) __('Commande n° %1', $orderIncrementId));
    }

    /**
     * Prix convenus d'un devis, par article : seule une ligne identique (article, options et quantité) en profite.
     *
     * @return array<string, array{qty: float, price: float}> par sku
     */
    public function getAgreedPrices(int $quoteId): array
    {
        $quote = $this->load($quoteId);
        if ($quote === null || $quote->getStatus() !== QuoteStatus::ACCEPTED || $this->isExpired($quote->getData())) {
            return [];
        }
        $prices = [];
        foreach ($this->quoteResource->getItems($quoteId) as $item) {
            $prices[(string) $item['sku']] = ['qty' => (float) $item['qty'], 'price' => (float) $item['unit_price_incl_tax']];
        }

        return $prices;
    }

    /**
     * Fait expirer les propositions et devis acceptés dont la validité est dépassée (tâche planifiée).
     */
    public function expireDue(): int
    {
        $connection = $this->quoteResource->connection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->quoteResource->getMainTable(), ['quote_id', 'store_id', 'status', 'valid_until'])
                ->where('status IN (?)', $this->quoteStatus->getExpirable())
                ->where('valid_until IS NOT NULL')
        );
        $expired = 0;
        foreach ($rows as $row) {
            if ($this->isExpired($row)) {
                $quote = $this->load((int) $row['quote_id']);
                if ($quote !== null) {
                    $this->expire($quote);
                    $expired++;
                }
            }
        }

        return $expired;
    }

    public function load(int $quoteId): ?Quote
    {
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $quoteId);

        return $quote->getId() ? $quote : null;
    }

    /**
     * Date dans la langue de la boutique du devis (le PDF est généré depuis l'administration, en anglais sinon).
     */
    public function formatDate(string $date, ?int $storeId = null): string
    {
        $locale = (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        $formatter = new \IntlDateFormatter($locale ?: 'fr_FR', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'UTC');

        return (string) $formatter->format(new \DateTimeImmutable(substr($date, 0, 10), new \DateTimeZone('UTC')));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isExpired(array $row): bool
    {
        return in_array($row['status'] ?? '', $this->quoteStatus->getExpirable(), true)
            && $this->calculator->isExpired(
                isset($row['valid_until']) ? (string) $row['valid_until'] : null,
                $this->today((int) ($row['store_id'] ?? 0))
            );
    }

    private function expire(Quote $quote): void
    {
        $quote->setData('status', QuoteStatus::EXPIRED);
        $this->quoteResource->save($quote);
        $this->quoteResource->addHistory((int) $quote->getId(), QuoteStatus::EXPIRED, (string) __('Système'), null);
    }

    private function today(int $storeId): string
    {
        return $this->timezone->scopeDate($storeId)->format('Y-m-d');
    }

    private function getTaxPercent(int $productId, Quote $quote): float
    {
        $product = $this->productRepository->getById($productId);
        $taxClass = $product instanceof Product ? (int) $product->getData('tax_class_id') : 0;

        return (float) $this->taxCalculation->getCalculatedRate($taxClass, $quote->getCustomerId(), (string) $quote->getData('store_id'));
    }
}

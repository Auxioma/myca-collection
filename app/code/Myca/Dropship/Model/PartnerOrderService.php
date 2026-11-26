<?php

declare(strict_types=1);

namespace Myca\Dropship\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option as QuoteItemOption;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Myca\Dropship\Model\Carrier\Professional;
use Myca\Dropship\Model\ResourceModel\DropshipOrder;
use Myca\Dropship\Model\ResourceModel\PartnerQuote;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Model\CurrentPartner;
use Myca\Core\Model\AuditLogger;
use Myca\Shipping\Model\Config as ShippingConfig;

/**
 * Commande d'une boutique partenaire (M17), seul point d'écriture :
 * 1. mode de vente (dropshipping ou achat de stock, selon les modes autorisés à la boutique) ;
 * 2. modèles et quantités par couleur et par taille, au tarif partenaire, dans la limite du stock vendable ;
 * 3. livraison : destinataire final (dropshipping) ou adresse de la boutique (achat de stock) ;
 * 4. validation : contrôles (minimum, stock, destination, conditions B2B), paiement, puis création de la commande.
 * Le panier est celui de la boutique ; il n'existe qu'une commande en cours par compte.
 */
class PartnerOrderService
{
    public const COLOR_ATTRIBUTE = 'myca_color';
    public const SIZE_ATTRIBUTE = 'myca_size';
    private const MAX_QTY_PER_SIZE = 10000;

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly CurrentPartner $currentPartner,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly CartManagementInterface $cartManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Configurable $configurableType,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly MethodList $methodList,
        private readonly PartnerQuote $partnerQuote,
        private readonly DropshipOrder $dropshipOrder,
        private readonly QuotePartner $quotePartner,
        private readonly OrderConfig $config,
        private readonly OrderRules $rules,
        private readonly ShippingConfig $shippingConfig,
        private readonly AuditLogger $audit
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function setMode(string $mode): void
    {
        $partner = $this->requirePartner();
        if (!in_array($mode, $partner->getSalesModes(), true)) {
            throw new LocalizedException(__('Ce mode de vente n’est pas ouvert à votre boutique.'));
        }
        $quote = $this->getQuote();
        $this->partnerQuote->save((int) $quote->getId(), (int) $partner->getId(), ['mode' => $mode]);
        $this->refresh($quote);
    }

    /**
     * Ajoute un modèle dans une couleur, avec une quantité par taille.
     *
     * @param array<int|string, int|string> $sizes identifiant d'option de taille => quantité
     * @throws LocalizedException
     */
    public function addModel(int $productId, int $colorId, array $sizes): void
    {
        $this->requirePartner();
        $quote = $this->getQuote();
        $product = $this->loadConfigurable($productId);
        $attributes = $this->getAttributeIds($product);
        $added = 0;
        foreach ($sizes as $sizeId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            if ($qty > self::MAX_QTY_PER_SIZE) {
                throw new LocalizedException(__('Quantité trop élevée pour une taille.'));
            }
            $superAttribute = [];
            if (isset($attributes[self::COLOR_ATTRIBUTE])) {
                $superAttribute[$attributes[self::COLOR_ATTRIBUTE]] = $colorId;
            }
            if (isset($attributes[self::SIZE_ATTRIBUTE])) {
                $superAttribute[$attributes[self::SIZE_ATTRIBUTE]] = (int) $sizeId;
            }
            $child = $this->configurableType->getProductByAttributes($superAttribute, $product);
            if ($child === null) {
                throw new LocalizedException(__('Cette taille n’existe pas dans cette couleur.'));
            }
            $wanted = $qty + $this->getQtyInQuote($quote, (string) $child->getSku());
            if ($wanted > $this->getSalableQty((string) $child->getSku())) {
                throw new LocalizedException(__(
                    'Stock insuffisant pour %1 (%2) : %3 pièce(s) disponible(s).',
                    $product->getName(),
                    $child->getAttributeText(self::SIZE_ATTRIBUTE),
                    $this->getSalableQty((string) $child->getSku())
                ));
            }
            // Une instance neuve par taille : Magento garde les options choisies sur l'instance du produit.
            $result = $quote->addProduct($this->loadConfigurable($productId), new DataObject(['qty' => $qty, 'super_attribute' => $superAttribute]));
            if (is_string($result)) {
                throw new LocalizedException(__($result));
            }
            $added += $qty;
        }
        if ($added === 0) {
            throw new LocalizedException(__('Indiquez au moins une quantité.'));
        }
        $this->refresh($quote);
    }

    /**
     * Retire un modèle dans une couleur (toutes ses tailles).
     *
     * @throws LocalizedException
     */
    public function removeModel(int $productId, int $colorId): void
    {
        $this->requirePartner();
        $quote = $this->getQuote();
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((int) $item->getData('product_id') === $productId && (int) $this->getItemOption($item, self::COLOR_ATTRIBUTE) === $colorId) {
                $quote->removeItem((int) $item->getId());
            }
        }
        $this->refresh($quote);
    }

    /**
     * Livraison : destinataire final (dropshipping) ou boutique (achat de stock), et prix de vente déclaré.
     *
     * @param array<string, mixed> $recipientInput
     * @throws LocalizedException
     */
    public function saveDelivery(array $recipientInput, ?float $declaredSaleAmount): void
    {
        $partner = $this->requirePartner();
        $quote = $this->getQuote();
        $mode = $this->quotePartner->getMode($quote, $partner);
        $recipient = null;
        if ($mode === PartnerPricing::MODE_DROPSHIP) {
            $result = $this->rules->normalizeRecipient($recipientInput, $this->getServedCountries());
            if ($result['errors'] !== []) {
                throw new LocalizedException(__(implode(' ', $result['errors'])));
            }
            $recipient = $result['recipient'];
        }
        if ($declaredSaleAmount !== null && $declaredSaleAmount < 0) {
            throw new LocalizedException(__('Le prix de vente déclaré ne peut pas être négatif.'));
        }
        // Le prix de vente déclaré ne concerne que le dropshipping (vente de la boutique à son client).
        $this->partnerQuote->save((int) $quote->getId(), (int) $partner->getId(), [
            'recipient' => $recipient,
            'declared_sale_amount' => $mode === PartnerPricing::MODE_DROPSHIP ? $declaredSaleAmount : null,
        ]);
        $this->applyAddresses($quote, $partner, $mode, $recipient);
        $this->refresh($quote);
    }

    /**
     * Valide la commande : contrôles, moyen de paiement, création. Une seconde validation du même panier est refusée.
     * Renvoie aussi l'adresse de la page du prestataire de paiement, s'il en fournit une.
     *
     * @return array{order: Order, redirect_url: string}
     * @throws LocalizedException
     */
    public function place(string $paymentMethod, bool $termsAccepted): array
    {
        $partner = $this->requirePartner();
        $quote = $this->getQuote();
        if (!$termsAccepted) {
            throw new LocalizedException(__('Acceptez les conditions de vente professionnelles pour valider.'));
        }
        $issues = $this->getBlockingIssues($quote, $partner);
        if ($issues !== []) {
            throw new LocalizedException(__(implode(' ', $issues)));
        }
        $mode = $this->quotePartner->getMode($quote, $partner);
        $stored = $this->partnerQuote->get((int) $quote->getId());
        $this->applyAddresses($quote, $partner, $mode, $stored['recipient'] ?? null);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();
        if (!$quote->getShippingAddress()->getShippingRateByCode(Professional::CODE . '_' . Professional::CODE)) {
            throw new LocalizedException(__('La livraison professionnelle n’est pas disponible pour cette destination.'));
        }
        $codes = array_map(static fn ($method) => $method->getCode(), $this->methodList->getAvailableMethods($quote));
        if (!in_array($paymentMethod, $codes, true)) {
            throw new LocalizedException(__('Choisissez un moyen de paiement proposé.'));
        }
        $quote->getPayment()->importData(['method' => $paymentMethod]);
        $quote->setCustomerNote((string) __(
            'Commande partenaire (%1). Conditions de vente professionnelles acceptées le %2.',
            $mode === PartnerPricing::MODE_STOCK ? __('achat de stock') : __('dropshipping'),
            gmdate('d/m/Y H:i') . ' UTC'
        ));
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $redirectUrl = (string) $quote->getPayment()->getOrderPlaceRedirectUrl();
        $orderId = (int) $this->cartManagement->placeOrder((int) $quote->getId());
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        $this->dropshipOrder->save(
            $orderId,
            (int) $partner->getId(),
            $mode,
            $mode === PartnerPricing::MODE_DROPSHIP ? ($stored['declared_sale_amount'] ?? null) : null,
            $mode === PartnerPricing::MODE_DROPSHIP
        );
        $this->partnerQuote->delete((int) $quote->getId());
        $this->audit->log('partner_order_placed', 'order', (string) $order->getIncrementId(), null, [
            'partner' => $partner->getCode(),
            'mode' => $mode,
            'grand_total' => (float) $order->getGrandTotal(),
            'payment' => $paymentMethod,
            'terms_accepted' => true,
        ]);

        return ['order' => $order, 'redirect_url' => $redirectUrl];
    }

    /**
     * État de la commande en cours, pour l'écran M17.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $partner = $this->requirePartner();
        // Panier relu en base puis recalculé : l'objet en mémoire a pu être modifié et calculé plusieurs fois.
        $quote = $this->getQuote();
        if ($quote->getId()) {
            $quote = $this->quoteFactory->create()->load((int) $quote->getId());
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
        }
        $mode = $this->quotePartner->getMode($quote, $partner);
        $stored = $quote->getId() ? $this->partnerQuote->get((int) $quote->getId()) : null;
        $lines = [];
        $publicExclTax = 0.0;
        foreach ($quote->getAllVisibleItems() as $item) {
            $colorId = (int) $this->getItemOption($item, self::COLOR_ATTRIBUTE);
            $size = $this->getItemOptionLabel($item, 'Taille');
            $key = $item->getData('product_id') . '|' . $colorId;
            $ratio = (float) $item->getRowTotalInclTax() > 0 ? (float) $item->getRowTotal() / (float) $item->getRowTotalInclTax() : 1.0;
            $public = (float) $item->getData('myca_public_price');
            $publicExclTax += $public * (float) $item->getQty() * $ratio;
            $lines[$key] ??= [
                'product_id' => (int) $item->getData('product_id'),
                'color_id' => $colorId,
                'name' => (string) $item->getName(),
                'color' => $this->getItemOptionLabel($item, 'Couleur'),
                'sizes' => [],
                'qty' => 0,
                'total_excl_tax' => 0.0,
                'discount' => (float) $item->getData('myca_partner_discount'),
            ];
            $lines[$key]['sizes'][] = ($size !== '' ? $size . ' ' : '') . '× ' . (int) $item->getQty();
            $lines[$key]['qty'] += (int) $item->getQty();
            $lines[$key]['total_excl_tax'] += (float) $item->getRowTotal();
        }
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $subtotal = (float) $address->getSubtotal();
        $shipping = (float) $address->getShippingAmount();

        return [
            'mode' => $mode,
            'modes' => array_values($partner->getSalesModes()),
            'lines' => array_values($lines),
            'totals' => [
                'public_excl_tax' => round($publicExclTax, 2),
                'discount' => round(max(0.0, $publicExclTax - $subtotal), 2),
                'subtotal_excl_tax' => round($subtotal, 2),
                'shipping' => round($shipping, 2),
                'shipping_known' => (string) $address->getShippingMethod() !== '',
                'tax' => round((float) $address->getTaxAmount(), 2),
                'grand_total' => round((float) $quote->getGrandTotal(), 2),
            ],
            'recipient' => $stored['recipient'] ?? null,
            'declared_sale_amount' => $stored['declared_sale_amount'] ?? null,
            'issues' => $this->getBlockingIssues($quote, $partner, false),
            'payment_methods' => array_map(
                static fn ($method) => ['code' => $method->getCode(), 'title' => (string) $method->getTitle()],
                array_values($quote->getItemsCount() ? $this->methodList->getAvailableMethods($quote) : [])
            ),
        ];
    }

    /**
     * @return string[] pays desservis (à domicile) pour la livraison professionnelle
     */
    public function getServedCountries(): array
    {
        return array_keys(array_filter($this->shippingConfig->getDestinations(), static fn (array $modes) => $modes['home']));
    }

    /**
     * @return string[]
     */
    private function getBlockingIssues(Quote $quote, PartnerInterface $partner, bool $withDelivery = true): array
    {
        $issues = [];
        if (!$quote->getItemsCount()) {
            return [(string) __('Ajoutez au moins un modèle à la commande.')];
        }
        $mode = $this->quotePartner->getMode($quote, $partner);
        if ($mode === PartnerPricing::MODE_STOCK) {
            $quantities = [];
            foreach ($quote->getAllVisibleItems() as $item) {
                $key = $item->getData('product_id') . '|' . (int) $this->getItemOption($item, self::COLOR_ATTRIBUTE);
                $quantities[$key] ??= ['label' => $item->getName() . ' – ' . $this->getItemOptionLabel($item, 'Couleur'), 'qty' => 0];
                $quantities[$key]['qty'] += (int) $item->getQty();
            }
            $minimum = $this->config->getStockMinimumPerColor();
            $below = $this->rules->getBelowMinimum($quantities, $minimum);
            if ($below !== []) {
                $issues[] = (string) __('Minimum de %1 pièces par modèle et par couleur non atteint : %2.', $minimum, implode(', ', $below));
            }
        }
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() === 'simple' && (float) $item->getQty() > $this->getSalableQty((string) $item->getSku())) {
                $issues[] = (string) __('Stock insuffisant pour %1 (%2).', $item->getName(), $item->getSku());
            }
        }
        if ($withDelivery) {
            $stored = $this->partnerQuote->get((int) $quote->getId());
            if ($mode === PartnerPricing::MODE_DROPSHIP && empty($stored['recipient'])) {
                $issues[] = (string) __('Renseignez le destinataire final (étape 3).');
            }
            if ($mode === PartnerPricing::MODE_STOCK && !$this->isBoutiqueAddressComplete($partner)) {
                $issues[] = (string) __('L’adresse de votre boutique est incomplète : complétez votre profil boutique.');
            }
        }

        return $issues;
    }

    /**
     * @param array<string, string>|null $recipient
     * @throws LocalizedException
     */
    private function applyAddresses(Quote $quote, PartnerInterface $partner, string $mode, ?array $recipient): void
    {
        $customer = $this->customerSession->getCustomerData();
        $boutique = $this->partnerData($partner);
        $billing = [
            'firstname' => (string) $customer->getFirstname(),
            'lastname' => (string) $customer->getLastname(),
            'company' => $partner->getCompanyName(),
            'street' => $boutique['street'],
            'postcode' => $boutique['postcode'],
            'city' => $boutique['city'],
            'country_id' => $boutique['country_id'],
            'telephone' => $boutique['phone'],
            'email' => (string) $customer->getEmail(),
            'vat_id' => $boutique['vat_id'],
        ];
        $quote->getBillingAddress()->addData($billing)->setCustomerAddressId(null)->setSaveInAddressBook(0);
        if ($mode === PartnerPricing::MODE_DROPSHIP) {
            if ($recipient === null) {
                return;
            }
            // Les e-mails de la commande vont à la boutique, vendeuse : jamais au client final.
            $shipping = $recipient + ['company' => '', 'email' => (string) $customer->getEmail()];
        } else {
            if (!$this->isBoutiqueAddressComplete($partner)) {
                throw new LocalizedException(__('L’adresse de votre boutique est incomplète : complétez votre profil boutique.'));
            }
            $shipping = $billing;
        }
        $quote->getShippingAddress()
            ->addData($shipping)
            ->setCustomerAddressId(null)
            ->setSaveInAddressBook(0)
            ->setSameAsBilling(0)
            ->setShippingMethod(Professional::CODE . '_' . Professional::CODE)
            ->setCollectShippingRates(true);
    }

    private function isBoutiqueAddressComplete(PartnerInterface $partner): bool
    {
        $data = $this->partnerData($partner);

        return $data['street'] !== '' && $data['postcode'] !== '' && $data['city'] !== '' && $data['country_id'] !== '';
    }

    /**
     * @return array{street: string, postcode: string, city: string, country_id: string, phone: string, vat_id: string}
     */
    private function partnerData(PartnerInterface $partner): array
    {
        $get = static fn (string $key): string => $partner instanceof AbstractModel ? trim((string) $partner->getData($key)) : '';

        return [
            'street' => $get('street'),
            'postcode' => $get('postcode'),
            'city' => $get('city'),
            'country_id' => $get('country_id'),
            'phone' => $get('phone'),
            'vat_id' => $get('vat_id'),
        ];
    }

    private function refresh(Quote $quote): void
    {
        $quote->setData('totals_collected_flag', false);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
    }

    /**
     * @throws LocalizedException
     */
    private function requirePartner(): PartnerInterface
    {
        $partner = $this->currentPartner->getActive();
        if ($partner === null || !$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Cet espace est réservé aux partenaires MYCA actifs.'));
        }

        return $partner;
    }

    private function getQuote(): Quote
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId()) {
            $quote->setCustomer($this->customerSession->getCustomerDataObject());
            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote);
        }

        return $quote;
    }

    /**
     * @throws LocalizedException
     */
    private function loadConfigurable(int $productId): Product
    {
        try {
            $product = $this->productRepository->getById($productId, false, (int) $this->storeManager->getStore()->getId(), true);
        } catch (\Exception) {
            throw new LocalizedException(__('Ce modèle n’existe pas.'));
        }
        if (!$product instanceof Product || $product->getTypeId() !== Configurable::TYPE_CODE || (int) $product->getStatus() !== 1) {
            throw new LocalizedException(__('Ce modèle n’est pas disponible à la commande.'));
        }

        return $product;
    }

    /**
     * Attributs de déclinaison (couleur et/ou taille) du modèle.
     *
     * @return array<string, int> code => identifiant d'attribut
     * @throws LocalizedException
     */
    private function getAttributeIds(Product $product): array
    {
        $ids = [];
        foreach ($this->configurableType->getConfigurableAttributesAsArray($product) as $attribute) {
            $code = (string) $attribute['attribute_code'];
            if (in_array($code, [self::COLOR_ATTRIBUTE, self::SIZE_ATTRIBUTE], true)) {
                $ids[$code] = (int) $attribute['attribute_id'];
            }
        }
        if ($ids === [] || count($ids) !== count($this->configurableType->getConfigurableAttributesAsArray($product))) {
            throw new LocalizedException(__('Ce modèle ne se commande pas par couleur et par taille.'));
        }

        return $ids;
    }

    private function getQtyInQuote(Quote $quote, string $sku): int
    {
        $qty = 0;
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() === 'simple' && (string) $item->getSku() === $sku) {
                $qty += (int) ($item->getParentItem() ? $item->getParentItem()->getQty() : $item->getQty());
            }
        }

        return $qty;
    }

    private function getSalableQty(string $sku): int
    {
        try {
            $websiteCode = (string) $this->storeManager->getWebsite()->getCode();
            $stockId = (int) $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();

            return (int) $this->getProductSalableQty->execute($sku, $stockId);
        } catch (LocalizedException) {
            return 0;
        }
    }

    /**
     * Valeur d'une option (couleur ou taille) choisie sur une ligne de produit configurable.
     */
    private function getItemOption(Item $item, string $attributeCode): ?int
    {
        $child = $this->getChild($item);
        if ($child === null) {
            return null;
        }
        $value = $child->getData($attributeCode) ?? $this->loadProduct((int) $child->getId())?->getData($attributeCode);

        return $value !== null ? (int) $value : null;
    }

    private function getItemOptionLabel(Item $item, string $label): string
    {
        $child = $this->getChild($item);
        $code = $label === 'Couleur' ? self::COLOR_ATTRIBUTE : self::SIZE_ATTRIBUTE;
        if ($child === null) {
            return '';
        }
        $text = $child->getAttributeText($code);
        if (!is_string($text) || $text === '') {
            $text = $this->loadProduct((int) $child->getId())?->getAttributeText($code);
        }

        return is_string($text) ? $text : '';
    }

    /**
     * Variante (produit simple) d'une ligne de produit configurable.
     */
    private function getChild(Item $item): ?Product
    {
        $option = $item->getOptionByCode('simple_product');
        $product = $option instanceof QuoteItemOption ? $option->getProduct() : null;

        return $product instanceof Product ? $product : null;
    }

    private function loadProduct(int $productId): ?Product
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (\Exception) {
            return null;
        }

        return $product instanceof Product ? $product : null;
    }
}

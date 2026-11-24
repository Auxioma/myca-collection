<?php

declare(strict_types=1);

namespace Myca\Quote\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Quote\Model\CustomerQuotes;
use Myca\Quote\Model\Quote;
use Myca\Quote\Model\QuoteCalculator;
use Myca\Quote\Model\QuoteManagement;
use Myca\Quote\Model\QuoteStatus;
use Myca\Quote\Model\ResourceModel\Quote as QuoteResource;

/**
 * M30 — devis du client connecté, et encart « Demander un devis » du panier.
 */
class Quotes implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerQuotes $customerQuotes,
        private readonly QuoteResource $quoteResource,
        private readonly QuoteStatus $quoteStatus,
        private readonly QuoteCalculator $calculator,
        private readonly QuoteManagement $quoteManagement,
        private readonly CustomerSession $customerSession,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper
    ) {
    }

    public function isLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * @return Quote[]
     */
    public function getQuotes(): array
    {
        return $this->customerQuotes->getList();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(Quote $quote): array
    {
        return $this->quoteResource->getItems((int) $quote->getId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Quote $quote): array
    {
        return $this->quoteResource->getHistory((int) $quote->getId());
    }

    /**
     * @return array{label: string, tone: string}
     */
    public function getStatus(Quote $quote): array
    {
        return $this->quoteStatus->getLabel($quote->getStatus());
    }

    public function isWaiting(Quote $quote): bool
    {
        return $this->quoteStatus->isWaiting($quote->getStatus());
    }

    public function isPriced(Quote $quote): bool
    {
        return $quote->getData('grand_total') !== null;
    }

    /**
     * Actions proposées au client selon le statut, dans l'ordre de la maquette.
     *
     * @return string[]
     */
    public function getActions(Quote $quote): array
    {
        return match ($quote->getStatus()) {
            QuoteStatus::DRAFT => ['envoyer', 'annuler'],
            QuoteStatus::REQUESTED => ['annuler'],
            QuoteStatus::PROPOSED => ['accepter', 'refuser'],
            QuoteStatus::ACCEPTED => ['commander', 'annuler'],
            default => [],
        };
    }

    /**
     * Étapes de suivi de la maquette, avec les dates réelles.
     *
     * @return array<int, array{label: string, date: ?string, done: bool}>
     */
    public function getSteps(Quote $quote): array
    {
        $dates = [];
        foreach ($this->getHistory($quote) as $entry) {
            $dates[(string) $entry['status']] ??= (string) $entry['created_at'];
        }
        $steps = [
            [(string) __('Demande envoyée'), QuoteStatus::REQUESTED],
            [(string) __('Devis proposé par MYCA'), QuoteStatus::PROPOSED],
            [(string) __('Devis accepté'), QuoteStatus::ACCEPTED],
            [(string) __('Transformation en commande'), QuoteStatus::ORDERED],
        ];
        $end = $quote->getStatus();
        $result = [];
        foreach ($steps as [$label, $status]) {
            $result[] = ['label' => $label, 'date' => $dates[$status] ?? null, 'done' => isset($dates[$status])];
        }
        if (in_array($end, [QuoteStatus::REFUSED, QuoteStatus::CANCELED, QuoteStatus::EXPIRED], true)) {
            $result = array_values(array_filter($result, static fn (array $step) => $step['done']));
            $result[] = ['label' => $this->quoteStatus->getLabel($end)['label'], 'date' => $dates[$end] ?? null, 'done' => true];
        }

        return $result;
    }

    /**
     * Résumé des articles, par exemple « 50 polos + 50 casquettes » : nom du premier article et nombre de lignes.
     */
    public function getSummary(Quote $quote): string
    {
        $items = $this->getItems($quote);
        $first = reset($items);
        if (!$first) {
            return '';
        }
        $qty = array_sum(array_map(static fn (array $item) => (float) $item['qty'], $items));

        return (string) __('%1 pièces · %2%3', $qty, $first['name'], count($items) > 1 ? ' +' . (count($items) - 1) : '');
    }

    public function exclTax(float $inclTax, float $taxPercent): float
    {
        return $this->calculator->exclTax($inclTax, $taxPercent);
    }

    public function formatPrice(?float $amount): string
    {
        return $amount === null ? '—' : (string) $this->priceCurrency->format($amount, false);
    }

    public function formatDate(?string $date): string
    {
        return $date ? $this->quoteManagement->formatDate($date) : '—';
    }

    public function getImageUrl(int $productId): string
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException) {
            return '';
        }

        return $product instanceof Product ? $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl() : '';
    }

    /**
     * @return array<string, int> nombre de devis par filtre de la maquette
     */
    public function getCounts(): array
    {
        $counts = ['tous' => 0, 'en-attente' => 0, 'acceptes' => 0, 'refuses' => 0, 'brouillons' => 0];
        foreach ($this->getQuotes() as $quote) {
            $counts['tous']++;
            foreach (array_keys($counts) as $filter) {
                if ($filter !== 'tous' && $this->matches($quote, $filter)) {
                    $counts[$filter]++;
                }
            }
        }

        return $counts;
    }

    public function matches(Quote $quote, string $filter): bool
    {
        return match ($filter) {
            'en-attente' => $this->quoteStatus->isWaiting($quote->getStatus()),
            'acceptes' => in_array($quote->getStatus(), [QuoteStatus::ACCEPTED, QuoteStatus::ORDERED], true),
            'refuses' => $quote->getStatus() === QuoteStatus::REFUSED,
            'brouillons' => $quote->getStatus() === QuoteStatus::DRAFT,
            default => true,
        };
    }
}

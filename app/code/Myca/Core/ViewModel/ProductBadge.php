<?php

declare(strict_types=1);

namespace Myca\Core\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Pastille de la carte produit, par ordre de priorité : rupture de stock, remise (« −20 % », prix final sous le prix de
 * référence), « Best-seller » (attribut myca_best_seller), puis « Nouveau » (dates natives de Magento).
 */
class ProductBadge implements ArgumentInterface
{
    public const BEST_SELLER_ATTRIBUTE = 'myca_best_seller';

    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return array{label: string, tone: string}|null
     */
    public function get(Product $product): ?array
    {
        if (!$product->isSalable()) {
            return ['label' => (string) __('Rupture'), 'tone' => 'neutral'];
        }

        $discount = $this->getDiscountPercent($product);
        if ($discount > 0) {
            // Signe moins typographique et espace fine insécable avant « % ».
            return ['label' => "\u{2212}" . $discount . "\u{202F}%", 'tone' => 'danger'];
        }

        if ($product->getData(self::BEST_SELLER_ATTRIBUTE)) {
            return ['label' => (string) __('Best-seller'), 'tone' => 'danger'];
        }

        if ($this->isNew($product)) {
            return ['label' => (string) __('Nouveau'), 'tone' => 'info'];
        }

        return null;
    }

    private function getDiscountPercent(Product $product): int
    {
        $priceInfo = $product->getPriceInfo();
        $regular = (float) $priceInfo->getPrice('regular_price')->getAmount()->getValue();
        $final = (float) $priceInfo->getPrice('final_price')->getAmount()->getValue();

        return $regular > 0 && $final < $regular ? (int) round((1 - $final / $regular) * 100) : 0;
    }

    private function isNew(Product $product): bool
    {
        $from = $product->getData('news_from_date');
        $to = $product->getData('news_to_date');

        if (!$from && !$to) {
            return false;
        }

        return $this->timezone->isScopeDateInInterval($product->getStore(), $from, $to);
    }
}

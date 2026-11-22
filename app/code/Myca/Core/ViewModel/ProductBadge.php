<?php

declare(strict_types=1);

namespace Myca\Core\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Pastille de la carte produit : « Best-seller » (attribut myca_best_seller) prioritaire sur
 * « Nouveau » (dates « Nouveau produit » natives de Magento).
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
        if ($product->getData(self::BEST_SELLER_ATTRIBUTE)) {
            return ['label' => (string) __('Best-seller'), 'tone' => 'danger'];
        }

        if ($this->isNew($product)) {
            return ['label' => (string) __('Nouveau'), 'tone' => 'info'];
        }

        return null;
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

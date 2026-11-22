<?php

declare(strict_types=1);

namespace Myca\Catalog\ViewModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Quantité vendable d'une variante d'après MSI (réservations comprises), pour l'alerte « Plus que N en stock ».
 */
class StockLevel implements ArgumentInterface
{
    public const LOW_STOCK_LIMIT = 3;

    public function __construct(
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Quantité restante si elle est faible (1 à 3), sinon null.
     */
    public function getLowStockQty(string $sku): ?int
    {
        try {
            $websiteCode = $this->storeManager->getWebsite()->getCode();
            $stockId = (int) $this->stockResolver
                ->execute(SalesChannelInterface::TYPE_WEBSITE, (string) $websiteCode)
                ->getStockId();
            $qty = (int) $this->getProductSalableQty->execute($sku, $stockId);
        } catch (LocalizedException) {
            return null;
        }

        return $qty > 0 && $qty <= self::LOW_STOCK_LIMIT ? $qty : null;
    }
}

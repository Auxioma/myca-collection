<?php

declare(strict_types=1);

namespace Myca\Dropship\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option as QuoteItemOption;
use Myca\Dropship\Model\OrderConfig;
use Myca\Dropship\Model\PartnerPricing;
use Myca\Dropship\Model\QuotePartner;

/**
 * Avant chaque calcul du panier d'une boutique : prix partenaire de chaque ligne, recalculé côté serveur depuis le
 * prix public conseillé, le mode de vente, la quantité du modèle et l'éventuelle remise contractuelle.
 * Un client B2C n'est jamais concerné.
 */
class ApplyPartnerPrices implements ObserverInterface
{
    public function __construct(
        private readonly QuotePartner $quotePartner,
        private readonly PartnerPricing $pricing,
        private readonly OrderConfig $config,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        $partner = $this->quotePartner->getPartner($quote);
        if ($partner === null) {
            return;
        }
        $mode = $this->quotePartner->getMode($quote, $partner);
        $contractField = $mode === PartnerPricing::MODE_STOCK ? 'stock_discount' : 'dropship_discount';
        $contract = $partner instanceof AbstractModel ? $partner->getData($contractField) : null;
        $contract = $contract !== null && $contract !== '' ? (float) $contract : null;

        $items = $quote->getAllVisibleItems();
        $modelQty = [];
        foreach ($items as $item) {
            $modelQty[(int) $item->getData('product_id')] = ($modelQty[(int) $item->getData('product_id')] ?? 0) + (int) $item->getQty();
        }
        foreach ($items as $item) {
            $discount = $this->pricing->getDiscount(
                $mode,
                $modelQty[(int) $item->getData('product_id')],
                $contract,
                $this->config->getDropshipDiscount(),
                $this->config->getStockTiers()
            );
            $public = $this->getPublicPrice($item);
            $price = $this->pricing->getUnitPrice($public, $discount);
            $item->setData('myca_public_price', $public);
            $item->setCustomPrice($price);
            $item->setOriginalCustomPrice($price);
            $item->setData('myca_partner_discount', $discount);
            $item->getProduct()->setData('is_super_mode', true);
        }
    }

    /**
     * Prix public conseillé (prix catalogue de la variante, hors promotion B2C).
     */
    private function getPublicPrice(Item $item): float
    {
        $option = $item->getOptionByCode('simple_product');
        $child = $option instanceof QuoteItemOption ? $option->getProduct() : null;
        $productId = $child !== null ? (int) $child->getId() : (int) $item->getData('product_id');
        try {
            // Prix lu dans le catalogue (instance mise en cache par le dépôt) : celui de la ligne peut déjà être remplacé.
            return (float) $this->productRepository->getById($productId)->getPrice();
        } catch (\Exception) {
            return 0.0;
        }
    }
}

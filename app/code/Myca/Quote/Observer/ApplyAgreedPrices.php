<?php

declare(strict_types=1);

namespace Myca\Quote\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote as Cart;
use Myca\Quote\Model\QuoteManagement;

/**
 * Applique les prix d'un devis accepté et encore valable aux seules lignes identiques (même article, mêmes
 * options, même quantité). Une ligne modifiée ou ajoutée revient au prix catalogue ; un devis expiré n'a plus
 * d'effet sur le panier.
 */
class ApplyAgreedPrices implements ObserverInterface
{
    public function __construct(private readonly QuoteManagement $quoteManagement)
    {
    }

    public function execute(Observer $observer): void
    {
        $cart = $observer->getEvent()->getData('quote');
        if (!$cart instanceof Cart || !$cart->getData('myca_quote_id')) {
            return;
        }
        $prices = $this->quoteManagement->getAgreedPrices((int) $cart->getData('myca_quote_id'));
        foreach ($cart->getAllVisibleItems() as $item) {
            $agreed = $prices[(string) $item->getSku()] ?? null;
            if ($agreed !== null && abs((float) $item->getQty() - $agreed['qty']) < 0.0001) {
                $item->setCustomPrice($agreed['price']);
                $item->setOriginalCustomPrice($agreed['price']);
                $item->getProduct()->setData('is_super_mode', true);
            } elseif ($item->getCustomPrice() !== null) {
                $item->unsetData('custom_price');
                $item->unsetData('original_custom_price');
            }
        }
        if (!$prices) {
            $cart->setData('myca_quote_id', null);
        }
    }
}

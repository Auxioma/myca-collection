<?php

declare(strict_types=1);

namespace Myca\Quote\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote as Cart;
use Magento\Sales\Model\Order;
use Myca\Quote\Model\QuoteManagement;

class MarkQuoteOrdered implements ObserverInterface
{
    public function __construct(private readonly QuoteManagement $quoteManagement)
    {
    }

    public function execute(Observer $observer): void
    {
        $cart = $observer->getEvent()->getData('quote');
        $order = $observer->getEvent()->getData('order');
        if ($cart instanceof Cart && $order instanceof Order && $cart->getData('myca_quote_id')) {
            $this->quoteManagement->markOrdered((int) $cart->getData('myca_quote_id'), (int) $order->getId(), (string) $order->getIncrementId());
        }
    }
}

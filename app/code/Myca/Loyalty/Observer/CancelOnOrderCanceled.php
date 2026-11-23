<?php

declare(strict_types=1);

namespace Myca\Loyalty\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Myca\Loyalty\Model\LoyaltyService;

class CancelOnOrderCanceled implements ObserverInterface
{
    public function __construct(private readonly LoyaltyService $loyaltyService)
    {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if ($order instanceof Order && $order->getId()) {
            $this->loyaltyService->cancelOrder($order);
        }
    }
}

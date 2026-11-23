<?php

declare(strict_types=1);

namespace Myca\Partner\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;
use Myca\Partner\Model\ResourceModel\PartnerOrder;

/**
 * Rattache une commande au partenaire de son client, au moment de sa création (vitrine, administration, API).
 * Le rattachement est figé : un client qui quitte un partenaire ne lui retire pas ses commandes passées.
 */
class LinkOrderToPartner implements ObserverInterface
{
    public function __construct(
        private readonly PartnerResource $partnerResource,
        private readonly PartnerOrder $partnerOrder
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof Order || !$order->getId() || !$order->getCustomerId()) {
            return;
        }
        if ($this->partnerOrder->getPartnerId((int) $order->getId()) !== null) {
            return;
        }
        $partnerId = $this->partnerResource->getPartnerIdByCustomerId((int) $order->getCustomerId());
        if ($partnerId !== null) {
            $this->partnerOrder->link((int) $order->getId(), $partnerId);
        }
    }
}

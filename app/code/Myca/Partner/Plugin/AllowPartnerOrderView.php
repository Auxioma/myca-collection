<?php

declare(strict_types=1);

namespace Myca\Partner\Plugin;

use Magento\Sales\Controller\AbstractController\OrderViewAuthorizationInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\ResourceModel\PartnerOrder;

/**
 * Cloisonnement des pages commande / facture / expédition de la vitrine : Magento n'autorise que le client
 * qui a passé la commande. Un utilisateur d'un partenaire actif voit aussi les commandes de sa boutique
 * passées par ses collègues — et jamais celles d'un autre partenaire.
 */
class AllowPartnerOrderView
{
    public function __construct(
        private readonly CurrentPartner $currentPartner,
        private readonly PartnerOrder $partnerOrder,
        private readonly OrderConfig $orderConfig
    ) {
    }

    public function afterCanView(OrderViewAuthorizationInterface $subject, bool $result, Order $order): bool
    {
        if ($result) {
            return true;
        }
        $partnerId = $this->currentPartner->getActiveId();
        if ($partnerId === null || !$order->getId()) {
            return false;
        }
        if (!in_array($order->getStatus(), $this->orderConfig->getVisibleOnFrontStatuses(), true)) {
            return false;
        }

        return $this->partnerOrder->getPartnerId((int) $order->getId()) === $partnerId;
    }
}

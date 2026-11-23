<?php

declare(strict_types=1);

namespace Myca\Core\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Myca\Core\Setup\Patch\Data\AddDeliveredOrderStatus;

/**
 * Statut lisible d'une commande et étapes de suivi (M28, M29) :
 * Commande validée → Préparation → Expédiée → Livrée, avec les dates réelles quand elles existent.
 */
class OrderProgress implements ArgumentInterface
{
    public const STEP_VALIDATED = 'validated';
    public const STEP_PREPARATION = 'preparation';
    public const STEP_SHIPPED = 'shipped';
    public const STEP_DELIVERED = 'delivered';

    /**
     * @return array{label: string, tone: string}
     */
    public function getStatus(OrderInterface $order): array
    {
        return match (true) {
            $order->getState() === Order::STATE_CANCELED => ['label' => (string) __('Annulée'), 'tone' => 'neutral'],
            $order->getState() === Order::STATE_CLOSED => ['label' => (string) __('Remboursée'), 'tone' => 'neutral'],
            $order->getStatus() === AddDeliveredOrderStatus::STATUS => ['label' => (string) __('Livrée'), 'tone' => 'success'],
            $this->isShipped($order) => ['label' => (string) __('Expédiée'), 'tone' => 'success'],
            in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PAYMENT_REVIEW], true)
                => ['label' => (string) __('En attente'), 'tone' => 'warning'],
            default => ['label' => (string) __('En préparation'), 'tone' => 'info'],
        };
    }

    public function isInProgress(OrderInterface $order): bool
    {
        return !in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_CLOSED], true)
            && $order->getStatus() !== AddDeliveredOrderStatus::STATUS;
    }

    /**
     * @return array<string, array{label: string, done: bool, date: ?string}>
     */
    public function getSteps(OrderInterface $order): array
    {
        $shipped = $this->isShipped($order);
        $delivered = $order->getStatus() === AddDeliveredOrderStatus::STATUS;
        $paid = $order->getState() !== Order::STATE_NEW && $order->getState() !== Order::STATE_PENDING_PAYMENT;

        return [
            self::STEP_VALIDATED => ['label' => (string) __('Commande validée'), 'done' => true, 'date' => $order->getCreatedAt()],
            self::STEP_PREPARATION => ['label' => (string) __('Préparation'), 'done' => $paid || $shipped, 'date' => null],
            self::STEP_SHIPPED => ['label' => (string) __('Expédiée'), 'done' => $shipped, 'date' => $this->getFirstShipmentDate($order)],
            self::STEP_DELIVERED => ['label' => (string) __('Livrée'), 'done' => $delivered, 'date' => $delivered ? $order->getUpdatedAt() : null],
        ];
    }

    private function isShipped(OrderInterface $order): bool
    {
        return $order instanceof Order && $order->hasShipments();
    }

    private function getFirstShipmentDate(OrderInterface $order): ?string
    {
        if (!$order instanceof Order || !$order->hasShipments()) {
            return null;
        }
        $shipments = $order->getShipmentsCollection();
        if (!$shipments) {
            return null;
        }
        $dates = [];
        foreach ($shipments as $shipment) {
            $dates[] = (string) $shipment->getData('created_at');
        }
        sort($dates);

        return $dates[0] ?? null;
    }
}

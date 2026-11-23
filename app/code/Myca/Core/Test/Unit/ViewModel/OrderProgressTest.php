<?php

declare(strict_types=1);

namespace Myca\Core\Test\Unit\ViewModel;

use Magento\Sales\Model\Order;
use Myca\Core\Setup\Patch\Data\AddDeliveredOrderStatus;
use Myca\Core\ViewModel\OrderProgress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderProgressTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool, string, string}>
     */
    public static function statusProvider(): array
    {
        return [
            'nouvelle commande' => [Order::STATE_NEW, 'pending', false, 'En attente', 'warning'],
            'payée, non expédiée' => [Order::STATE_PROCESSING, 'processing', false, 'En préparation', 'info'],
            'expédiée' => [Order::STATE_COMPLETE, 'complete', true, 'Expédiée', 'success'],
            'livrée' => [Order::STATE_COMPLETE, AddDeliveredOrderStatus::STATUS, true, 'Livrée', 'success'],
            'annulée' => [Order::STATE_CANCELED, 'canceled', false, 'Annulée', 'neutral'],
            'remboursée' => [Order::STATE_CLOSED, 'closed', true, 'Remboursée', 'neutral'],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testStatusLabel(string $state, string $status, bool $shipped, string $label, string $tone): void
    {
        $result = (new OrderProgress())->getStatus($this->createOrder($state, $status, $shipped));

        $this->assertSame($label, $result['label']);
        $this->assertSame($tone, $result['tone']);
    }

    public function testDeliveredOrderIsNoLongerInProgress(): void
    {
        $progress = new OrderProgress();

        $this->assertTrue($progress->isInProgress($this->createOrder(Order::STATE_COMPLETE, 'complete', true)));
        $this->assertFalse($progress->isInProgress($this->createOrder(Order::STATE_COMPLETE, AddDeliveredOrderStatus::STATUS, true)));
        $this->assertFalse($progress->isInProgress($this->createOrder(Order::STATE_CANCELED, 'canceled', false)));
    }

    public function testStepsOfAPendingOrder(): void
    {
        $steps = (new OrderProgress())->getSteps($this->createOrder(Order::STATE_NEW, 'pending', false));

        $this->assertTrue($steps[OrderProgress::STEP_VALIDATED]['done']);
        $this->assertFalse($steps[OrderProgress::STEP_PREPARATION]['done']);
        $this->assertFalse($steps[OrderProgress::STEP_SHIPPED]['done']);
        $this->assertFalse($steps[OrderProgress::STEP_DELIVERED]['done']);
    }

    private function createOrder(string $state, string $status, bool $shipped): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getState')->willReturn($state);
        $order->method('getStatus')->willReturn($status);
        $order->method('hasShipments')->willReturn($shipped);
        $order->method('getCreatedAt')->willReturn('2026-11-20 10:00:00');

        return $order;
    }
}

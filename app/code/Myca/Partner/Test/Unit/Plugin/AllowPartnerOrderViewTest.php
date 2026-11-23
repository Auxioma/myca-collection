<?php

declare(strict_types=1);

namespace Myca\Partner\Test\Unit\Plugin;

use Magento\Sales\Controller\AbstractController\OrderViewAuthorizationInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\ResourceModel\PartnerOrder;
use Myca\Partner\Plugin\AllowPartnerOrderView;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Cloisonnement : un utilisateur partenaire voit les commandes de SA boutique, jamais celles d'une autre.
 */
#[AllowMockObjectsWithoutExpectations]
class AllowPartnerOrderViewTest extends TestCase
{
    private const MY_PARTNER = 5;
    private const OTHER_PARTNER = 6;

    public function testMagentoAuthorizationIsKept(): void
    {
        $this->assertTrue($this->plugin(self::MY_PARTNER, self::OTHER_PARTNER)->afterCanView($this->subject(), true, $this->order()));
    }

    public function testColleagueOrderOfSamePartnerIsVisible(): void
    {
        $this->assertTrue($this->plugin(self::MY_PARTNER, self::MY_PARTNER)->afterCanView($this->subject(), false, $this->order()));
    }

    public function testOrderOfAnotherPartnerIsRefused(): void
    {
        $this->assertFalse($this->plugin(self::MY_PARTNER, self::OTHER_PARTNER)->afterCanView($this->subject(), false, $this->order()));
    }

    public function testOrderWithoutPartnerIsRefused(): void
    {
        $this->assertFalse($this->plugin(self::MY_PARTNER, null)->afterCanView($this->subject(), false, $this->order()));
    }

    public function testCustomerWithoutActivePartnerIsRefused(): void
    {
        $this->assertFalse($this->plugin(null, self::MY_PARTNER)->afterCanView($this->subject(), false, $this->order()));
    }

    public function testStatusHiddenOnStorefrontIsRefused(): void
    {
        $this->assertFalse($this->plugin(self::MY_PARTNER, self::MY_PARTNER)->afterCanView($this->subject(), false, $this->order('fraud')));
    }

    private function plugin(?int $currentPartnerId, ?int $orderPartnerId): AllowPartnerOrderView
    {
        $currentPartner = $this->createMock(CurrentPartner::class);
        $currentPartner->method('getActiveId')->willReturn($currentPartnerId);
        $partnerOrder = $this->createMock(PartnerOrder::class);
        $partnerOrder->method('getPartnerId')->with(42)->willReturn($orderPartnerId);
        $orderConfig = $this->createMock(OrderConfig::class);
        $orderConfig->method('getVisibleOnFrontStatuses')->willReturn(['pending', 'processing', 'complete']);

        return new AllowPartnerOrderView($currentPartner, $partnerOrder, $orderConfig);
    }

    private function subject(): OrderViewAuthorizationInterface
    {
        return $this->createMock(OrderViewAuthorizationInterface::class);
    }

    private function order(string $status = 'processing'): Order
    {
        $order = $this->createPartialMock(Order::class, []);
        $order->setData(['id' => 42, 'entity_id' => 42, 'status' => $status]);

        return $order;
    }
}

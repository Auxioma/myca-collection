<?php

declare(strict_types=1);

namespace Myca\Rma\Test\Unit\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Myca\Rma\Model\OrderEligibility;
use Myca\Rma\Model\Reasons;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;
use Myca\Rma\Model\ReturnPolicy;
use Myca\Rma\Model\Rma;
use Myca\Rma\Model\RmaFactory;
use Myca\Rma\Model\RmaManagement;
use Myca\Rma\Model\StatusFlow;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Les doublures partagées par setUp() ne portent des attentes que dans les tests qui en ont besoin.
 */
#[AllowMockObjectsWithoutExpectations]
class RmaManagementTest extends TestCase
{
    private RmaResource&MockObject $rmaResource;
    private OrderEligibility&MockObject $eligibility;
    private CreditmemoFactory&MockObject $creditmemoFactory;
    private RmaManagement $management;

    protected function setUp(): void
    {
        $this->rmaResource = $this->createMock(RmaResource::class);
        $this->rmaResource->method('connection')->willReturn($this->createStub(AdapterInterface::class));
        $this->eligibility = $this->createMock(OrderEligibility::class);
        $this->creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $this->management = new RmaManagement(
            $this->createStub(RmaFactory::class),
            $this->rmaResource,
            new ReturnPolicy(),
            new StatusFlow(),
            new Reasons(),
            $this->eligibility,
            $this->createStub(OrderRepositoryInterface::class),
            $this->creditmemoFactory,
            $this->createStub(CreditmemoManagementInterface::class)
        );
    }

    public function testReplayedRefundReturnsTheExistingCreditmemo(): void
    {
        $this->rmaResource->method('lockRow')->willReturn(['creditmemo_id' => '55', 'status' => StatusFlow::REFUNDED]);
        $this->creditmemoFactory->expects($this->never())->method('createByOrder');

        $this->assertSame(55, $this->management->refund($this->rma(ReturnPolicy::ORIGIN_ONLINE, ReturnPolicy::TYPE_RETURN), 'SAV'));
    }

    public function testStoreReturnNeverOpensAMycaRefund(): void
    {
        $this->rmaResource->expects($this->never())->method('lockRow');
        $this->expectException(LocalizedException::class);

        $this->management->refund($this->rma(ReturnPolicy::ORIGIN_STORE, ReturnPolicy::TYPE_RETURN), 'SAV');
    }

    public function testRefundRequiresInspection(): void
    {
        $this->rmaResource->method('lockRow')->willReturn(['creditmemo_id' => null, 'status' => StatusFlow::RECEIVED]);
        $this->creditmemoFactory->expects($this->never())->method('createByOrder');
        $this->expectException(LocalizedException::class);

        $this->management->refund($this->rma(ReturnPolicy::ORIGIN_DROPSHIP, ReturnPolicy::TYPE_RETURN), 'SAV');
    }

    public function testPartnerCannotFileAReturnForAnOnlineCustomerOrder(): void
    {
        $this->eligibility->method('getOrigin')->willReturn(ReturnPolicy::ORIGIN_ONLINE);
        $this->expectExceptionMessage('espace qui a passé la commande');

        $this->create(RmaManagement::AUTHOR_PARTNER);
    }

    public function testCustomerPortalRefusesAPartnerOrder(): void
    {
        $this->eligibility->method('getOrigin')->willReturn(ReturnPolicy::ORIGIN_DROPSHIP);
        $this->expectExceptionMessage('espace qui a passé la commande');

        $this->create(RmaManagement::AUTHOR_CUSTOMER);
    }

    public function testStoreSaleOnlyAcceptsAWarrantyClaim(): void
    {
        $this->eligibility->method('getOrigin')->willReturn(ReturnPolicy::ORIGIN_STORE);
        $this->expectExceptionMessage('comptoir');

        $this->create(RmaManagement::AUTHOR_PARTNER);
    }

    private function create(string $author): void
    {
        $this->management->create($this->createStub(Order::class), ReturnPolicy::TYPE_RETURN, 'size', [1 => 1], [], '', $author, 'Nom', 1);
    }

    private function rma(string $origin, string $type): Rma
    {
        $rma = $this->getMockBuilder(Rma::class)->disableOriginalConstructor()->onlyMethods(['getId'])->getMock();
        $rma->method('getId')->willReturn(7);
        $rma->setData(['rma_id' => 7, 'origin' => $origin, 'type' => $type, 'increment_id' => 'SAV-2026-0007', 'order_id' => 3]);

        return $rma;
    }
}

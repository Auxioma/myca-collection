<?php

declare(strict_types=1);

namespace Myca\Rma\Test\Unit\Model;

use Myca\Rma\Model\StatusFlow;
use PHPUnit\Framework\TestCase;

class StatusFlowTest extends TestCase
{
    private StatusFlow $flow;

    protected function setUp(): void
    {
        $this->flow = new StatusFlow();
    }

    public function testRequesterCanOnlyCancelOrSignalShipment(): void
    {
        $this->assertSame([StatusFlow::CANCELED], $this->flow->getAllowedTransitions(StatusFlow::CREATED, StatusFlow::ACTOR_REQUESTER));
        $this->assertTrue($this->flow->canTransition(StatusFlow::AUTHORIZED, StatusFlow::IN_TRANSIT, StatusFlow::ACTOR_REQUESTER));
        $this->assertFalse($this->flow->canTransition(StatusFlow::CREATED, StatusFlow::AUTHORIZED, StatusFlow::ACTOR_REQUESTER));
        $this->assertFalse($this->flow->canTransition(StatusFlow::IN_TRANSIT, StatusFlow::CANCELED, StatusFlow::ACTOR_REQUESTER), 'colis parti');
    }

    public function testNothingIsRefundedBeforeInspection(): void
    {
        $this->assertFalse($this->flow->canRefund(StatusFlow::RECEIVED));
        $this->assertTrue($this->flow->canRefund(StatusFlow::INSPECTED));
        $this->assertFalse($this->flow->canTransition(StatusFlow::INSPECTED, StatusFlow::REFUNDED, StatusFlow::ACTOR_ADMIN), 'seul refund() rembourse');
    }

    public function testClosedRequestIsFinal(): void
    {
        $this->assertSame([], $this->flow->getAllowedTransitions(StatusFlow::CLOSED, StatusFlow::ACTOR_ADMIN));
        $this->assertFalse($this->flow->isOpen(StatusFlow::CLOSED));
    }

    public function testCanceledRequestReleasesQuantities(): void
    {
        $this->assertFalse($this->flow->holdsQuantities(StatusFlow::CANCELED));
        $this->assertFalse($this->flow->holdsQuantities(StatusFlow::REJECTED));
        $this->assertTrue($this->flow->holdsQuantities(StatusFlow::CLOSED));
    }
}

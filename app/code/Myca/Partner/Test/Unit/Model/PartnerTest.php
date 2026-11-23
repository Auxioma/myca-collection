<?php

declare(strict_types=1);

namespace Myca\Partner\Test\Unit\Model;

use Myca\Partner\Model\Partner;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PartnerTest extends TestCase
{
    public function testOnlyActivePartnerOpensThePartnerSpace(): void
    {
        $partner = $this->partner();
        $this->assertFalse($partner->isActive(), 'nouveau partenaire : en attente');
        foreach (['pending' => false, 'active' => true, 'suspended' => false, 'rejected' => false] as $status => $active) {
            $this->assertSame($active, $partner->setStatus($status)->isActive(), $status);
        }
    }

    public function testUnknownStatusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->partner()->setStatus('vip');
    }

    public function testSalesModesKeepOnlyKnownValues(): void
    {
        $partner = $this->partner()->setSalesModes(['stock', 'marketplace', 'dropship']);
        $this->assertSame('dropship,stock', $partner->getData('sales_modes'));
        $this->assertSame(['dropship', 'stock'], $partner->getSalesModes());
        $this->assertSame([], $this->partner()->setData('sales_modes', '')->getSalesModes());
    }

    private function partner(): Partner
    {
        return $this->createPartialMock(Partner::class, ['_construct']);
    }
}

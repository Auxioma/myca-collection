<?php

declare(strict_types=1);

namespace Myca\Dropship\Test\Unit\Model;

use Myca\Dropship\Model\PartnerPricing;
use PHPUnit\Framework\TestCase;

class PartnerPricingTest extends TestCase
{
    private const TIERS = [['min_qty' => 300, 'discount' => 15.0], ['min_qty' => 50, 'discount' => 5.0], ['min_qty' => 100, 'discount' => 10.0]];

    private PartnerPricing $pricing;

    protected function setUp(): void
    {
        $this->pricing = new PartnerPricing();
    }

    public function testStockTiersPickHighestReachedWhateverTheOrder(): void
    {
        $this->assertSame(0.0, $this->pricing->getDiscount('stock', 49, null, 0, self::TIERS));
        $this->assertSame(5.0, $this->pricing->getDiscount('stock', 50, null, 0, self::TIERS));
        $this->assertSame(10.0, $this->pricing->getDiscount('stock', 299, null, 0, self::TIERS));
        $this->assertSame(15.0, $this->pricing->getDiscount('stock', 300, null, 0, self::TIERS));
    }

    public function testDropshipUsesItsOwnDiscountNotTheTiers(): void
    {
        $this->assertSame(8.0, $this->pricing->getDiscount('dropship', 500, null, 8, self::TIERS));
    }

    public function testContractDiscountReplacesTheGrid(): void
    {
        $this->assertSame(12.0, $this->pricing->getDiscount('stock', 10, 12.0, 0, self::TIERS));
        $this->assertSame(0.0, $this->pricing->getDiscount('dropship', 1, 0.0, 8, []));
    }

    public function testDiscountIsBoundedAndPriceRounded(): void
    {
        $this->assertSame(90.0, $this->pricing->getDiscount('dropship', 1, 150.0, 0, []));
        $this->assertSame(71.91, $this->pricing->getUnitPrice(79.90, 10));
        $this->assertSame(79.9, $this->pricing->getUnitPrice(79.90, -5));
    }
}

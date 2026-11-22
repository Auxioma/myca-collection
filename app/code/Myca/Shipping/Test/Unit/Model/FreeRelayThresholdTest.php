<?php

declare(strict_types=1);

namespace Myca\Shipping\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item;
use Myca\Shipping\Model\Config;
use Myca\Shipping\Model\FreeRelayThreshold;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Les lignes de panier sont des doubles partiels : on garde le vrai setData() de Magento.
#[AllowMockObjectsWithoutExpectations]
class FreeRelayThresholdTest extends TestCase
{
    /**
     * @return array<string, array{float, bool}>
     */
    public static function thresholdProvider(): array
    {
        return [
            '99,99 € : non atteint' => [99.99, false],
            '100,00 € : atteint' => [100.00, true],
            '100,01 € : atteint' => [100.01, true],
        ];
    }

    #[DataProvider('thresholdProvider')]
    public function testThresholdBoundaries(float $rowTotal, bool $expected): void
    {
        $threshold = $this->createThreshold();
        $amount = $threshold->getEligibleAmount([$this->createItem($rowTotal)]);

        $this->assertSame($expected, $threshold->isReached($amount));
    }

    public function testDiscountBringsCartBelowThreshold(): void
    {
        $threshold = $this->createThreshold();
        $amount = $threshold->getEligibleAmount([$this->createItem(110.00, 15.00)]);

        $this->assertSame(95.0, $amount);
        $this->assertFalse($threshold->isReached($amount));
        $this->assertSame(5.0, $threshold->getRemaining($amount));
    }

    public function testNonEligibleProductIsExcluded(): void
    {
        $threshold = $this->createThreshold();
        $amount = $threshold->getEligibleAmount([
            $this->createItem(80.00),
            $this->createItem(40.00, 0.0, false),
        ]);

        $this->assertSame(80.0, $amount);
        $this->assertFalse($threshold->isReached($amount));
    }

    public function testChildLinesAreNotCountedTwice(): void
    {
        $parent = $this->createItem(79.90);
        $child = $this->createItem(79.90);
        $child->setParentItem($parent);

        $this->assertSame(79.9, $this->createThreshold()->getEligibleAmount([$parent, $child]));
    }

    private function createThreshold(): FreeRelayThreshold
    {
        $config = $this->createStub(Config::class);
        $config->method('getFreeRelayThreshold')->willReturn(100.00);

        return new FreeRelayThreshold($config);
    }

    private function createItem(float $rowTotalInclTax, float $discount = 0.0, ?bool $eligible = null): Item
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturn($eligible === null ? null : (int) $eligible);

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProduct'])
            ->getMock();
        $item->method('getProduct')->willReturn($product);
        $item->setData('row_total_incl_tax', $rowTotalInclTax);
        $item->setData('discount_amount', $discount);

        return $item;
    }
}

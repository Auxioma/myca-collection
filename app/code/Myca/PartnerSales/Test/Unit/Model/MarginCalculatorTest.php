<?php

declare(strict_types=1);

namespace Myca\PartnerSales\Test\Unit\Model;

use Myca\PartnerSales\Model\MarginCalculator;
use PHPUnit\Framework\TestCase;

class MarginCalculatorTest extends TestCase
{
    private MarginCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new MarginCalculator();
    }

    public function testAllocationKeepsEveryCent(): void
    {
        $shares = $this->calculator->allocate(100.0, [1 => 1.0, 2 => 1.0, 3 => 1.0]);
        $this->assertSame([1 => 33.33, 2 => 33.33, 3 => 33.34], $shares);
        $this->assertSame([5 => 0.0], $this->calculator->allocate(10.0, [5 => 0.0]), 'rien à répartir');
    }

    public function testUndeclaredSalesAreExcludedFromMargins(): void
    {
        $lines = [
            $this->line(1, 100, 10, 2.0, 40.0, 100.0),
            $this->line(2, 100, 10, 1.0, 20.0, null),
        ];
        $summary = $this->calculator->summarize($lines, 0, 1000);
        $this->assertSame(100.0, $summary['revenue']);
        $this->assertSame(60.0, $summary['cost'], 'le coût total compte toutes les ventes');
        $this->assertSame(60.0, $summary['margin'], 'la marge ne compte que les ventes déclarées');
        $this->assertSame(60.0, $summary['rate']);
        $this->assertSame(1, $summary['undeclared']);
        $this->assertSame(3.0, $summary['qty']);
        $this->assertSame(30.0, $summary['margin_per_item']);
        $this->assertSame(150.0, $summary['profitability']);
    }

    public function testNothingDeclaredGivesNoRate(): void
    {
        $summary = $this->calculator->summarize([$this->line(1, 100, 10, 1.0, 20.0, null)], 0, 1000);
        $this->assertNull($summary['rate']);
        $this->assertNull($summary['basket']);
        $this->assertSame(0.0, $summary['margin']);
    }

    public function testMarginsByModelAndPeriod(): void
    {
        $lines = [
            $this->line(1, 100, 10, 2.0, 32.0, 99.8),
            $this->line(2, 200, 10, 1.0, 16.0, 49.9),
            $this->line(3, 100, 20, 1.0, 10.0, 36.9),
            $this->line(4, 5000, 10, 9.0, 99.0, 999.0),
        ];
        $products = $this->calculator->byProduct($lines, 0, 1000);
        $this->assertCount(2, $products, 'la vente hors période est ignorée');
        $this->assertSame(10, $products[0]['product_id']);
        $this->assertSame(3.0, $products[0]['qty']);
        $this->assertSame(49.9, $products[0]['unit_sale']);
        $this->assertSame(101.7, $products[0]['margin']);
        $this->assertNull($this->calculator->variation(10.0, 0.0));
        $this->assertSame(50, $this->calculator->variation(150.0, 100.0));
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $orderId, int $time, int $productId, float $qty, float $cost, ?float $sale): array
    {
        return ['order_id' => $orderId, 'time' => $time, 'product_id' => $productId, 'name' => 'Modèle ' . $productId,
            'qty' => $qty, 'cost' => $cost, 'sale' => $sale, 'source' => $sale === null ? null : 'price_list'];
    }
}

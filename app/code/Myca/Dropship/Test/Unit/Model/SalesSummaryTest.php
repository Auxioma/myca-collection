<?php

declare(strict_types=1);

namespace Myca\Dropship\Test\Unit\Model;

use Magento\Sales\Model\Order;
use Myca\Dropship\Model\SalesSummary;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Marge estimée = prix de vente HT déclaré − achat MYCA HT, sur les seules commandes déclarées.
 */
#[AllowMockObjectsWithoutExpectations]
class SalesSummaryTest extends TestCase
{
    private const FROM = 1_000_000;
    private const TO = 2_000_000;

    public function testMarginOnDeclaredOrdersOnly(): void
    {
        $orders = [
            $this->order(1, 120.0, 20.0, self::FROM + 10),     // achat 100 HT, vendu 150
            $this->order(2, 60.0, 10.0, self::FROM + 20),      // achat 50 HT, non déclarée
            $this->order(3, 240.0, 40.0, self::FROM - 1),      // hors période
            $this->order(4, 120.0, 20.0, self::FROM + 30, Order::STATE_CANCELED),
        ];
        $declarations = [
            1 => ['mode' => 'dropship', 'declared_sale_amount' => 150.0],
            3 => ['mode' => 'dropship', 'declared_sale_amount' => 999.0],
            4 => ['mode' => 'dropship', 'declared_sale_amount' => 999.0],
        ];

        $summary = (new SalesSummary())->summarize($orders, $declarations, self::FROM, self::TO);

        $this->assertSame(['orders' => 2, 'purchases' => 150.0, 'sales' => 150.0, 'margin' => 50.0], $summary);
    }

    public function testNothingDeclaredGivesNoSalesNorMargin(): void
    {
        $summary = (new SalesSummary())->summarize([$this->order(1, 120.0, 20.0, self::FROM)], [], self::FROM, self::TO);

        $this->assertNull($summary['sales']);
        $this->assertNull($summary['margin']);
        $this->assertSame(100.0, $summary['purchases']);
    }

    public function testVariation(): void
    {
        $summary = new SalesSummary();
        $this->assertSame(12, $summary->variation(112.0, 100.0));
        $this->assertSame(-50, $summary->variation(50.0, 100.0));
        $this->assertNull($summary->variation(50.0, null));
        $this->assertNull($summary->variation(50.0, 0.0));
        $this->assertNull($summary->variation(null, 100.0));
    }

    private function order(int $id, float $grandTotal, float $tax, int $createdAt, string $state = Order::STATE_PROCESSING): Order
    {
        $order = $this->createPartialMock(Order::class, []);
        $order->setData([
            'id' => $id,
            'entity_id' => $id,
            'grand_total' => $grandTotal,
            'tax_amount' => $tax,
            'created_at' => date('Y-m-d H:i:s', $createdAt),
            'state' => $state,
        ]);

        return $order;
    }
}

<?php

declare(strict_types=1);

namespace Myca\Core\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Myca\Core\ViewModel\ProductBadge;
use PHPUnit\Framework\TestCase;

class ProductBadgeTest extends TestCase
{
    public function testOutOfStockWinsOverEverything(): void
    {
        $badge = $this->createBadge(true)->get($this->createProduct(true, '2026-01-01', 79.90, 63.92, false));

        $this->assertNotNull($badge);
        $this->assertSame('Rupture', $badge['label']);
        $this->assertSame('neutral', $badge['tone']);
    }

    public function testDiscountWinsOverBestSellerAndNew(): void
    {
        $badge = $this->createBadge(true)->get($this->createProduct(true, '2026-01-01', 79.90, 63.92));

        $this->assertNotNull($badge);
        $this->assertSame("\u{2212}20\u{202F}%", $badge['label']);
        $this->assertSame('danger', $badge['tone']);
    }

    public function testBestSellerWinsOverNew(): void
    {
        $badge = $this->createBadge(true)->get($this->createProduct(true, '2026-01-01'));

        $this->assertNotNull($badge);
        $this->assertSame('Best-seller', $badge['label']);
    }

    public function testNewProductInsideInterval(): void
    {
        $badge = $this->createBadge(true)->get($this->createProduct(false, '2026-01-01'));

        $this->assertNotNull($badge);
        $this->assertSame('info', $badge['tone']);
    }

    public function testProductWithoutDatesHasNoBadge(): void
    {
        $this->assertNull($this->createBadge(true)->get($this->createProduct(false, null)));
    }

    public function testProductOutsideIntervalHasNoBadge(): void
    {
        $this->assertNull($this->createBadge(false)->get($this->createProduct(false, '2020-01-01')));
    }

    private function createBadge(bool $inInterval): ProductBadge
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('isScopeDateInInterval')->willReturn($inInterval);

        return new ProductBadge($timezone);
    }

    private function createProduct(
        bool $bestSeller,
        ?string $newsFrom,
        float $regular = 79.90,
        ?float $final = null,
        bool $salable = true
    ): Product {
        $priceInfo = $this->createStub(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->willReturnMap([
            ['regular_price', $this->createPrice($regular)],
            ['final_price', $this->createPrice($final ?? $regular)],
        ]);

        $product = $this->createStub(Product::class);
        $product->method('isSalable')->willReturn($salable);
        $product->method('getPriceInfo')->willReturn($priceInfo);
        $product->method('getData')->willReturnMap([
            [ProductBadge::BEST_SELLER_ATTRIBUTE, null, $bestSeller ? '1' : null],
            ['news_from_date', null, $newsFrom],
            ['news_to_date', null, null],
        ]);

        return $product;
    }

    private function createPrice(float $value): PriceInterface
    {
        $amount = $this->createStub(AmountInterface::class);
        $amount->method('getValue')->willReturn($value);
        $price = $this->createStub(PriceInterface::class);
        $price->method('getAmount')->willReturn($amount);

        return $price;
    }
}

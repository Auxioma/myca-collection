<?php

declare(strict_types=1);

namespace Myca\Core\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Myca\Core\ViewModel\ProductBadge;
use PHPUnit\Framework\TestCase;

class ProductBadgeTest extends TestCase
{
    public function testBestSellerWinsOverNew(): void
    {
        $badge = $this->createBadge(true)->get($this->createProduct(true, '2026-01-01'));

        $this->assertNotNull($badge);
        $this->assertSame('danger', $badge['tone']);
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

    private function createProduct(bool $bestSeller, ?string $newsFrom): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturnMap([
            [ProductBadge::BEST_SELLER_ATTRIBUTE, null, $bestSeller ? '1' : null],
            ['news_from_date', null, $newsFrom],
            ['news_to_date', null, null],
        ]);

        return $product;
    }
}

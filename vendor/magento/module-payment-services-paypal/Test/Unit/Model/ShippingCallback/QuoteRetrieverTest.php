<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\ShippingCallback;

use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteRetriever;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteRetrieverTest extends TestCase
{
    /**
     * @var MaskedQuoteIdToQuoteIdInterface|MockObject
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var QuoteRetriever
     */
    private QuoteRetriever $quoteRetriever;

    protected function setUp(): void
    {
        $this->maskedQuoteIdToQuoteId = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->quoteRetriever = new QuoteRetriever(
            $this->maskedQuoteIdToQuoteId,
            $this->cartRepository
        );
    }

    public function testGetQuoteByMaskedIdReturnsActiveQuoteWithItems(): void
    {
        $quote = $this->createQuote(123, ['item']);

        $this->maskedQuoteIdToQuoteId->expects($this->once())
            ->method('execute')
            ->with('masked-cart-id')
            ->willReturn(123);
        $this->cartRepository->expects($this->once())
            ->method('getActive')
            ->with(123)
            ->willReturn($quote);

        $this->assertSame($quote, $this->quoteRetriever->getQuoteByMaskedId('masked-cart-id'));
    }

    public function testGetQuoteByMaskedIdThrowsWhenQuoteHasNoItems(): void
    {
        $quote = $this->createQuote(123, []);

        $this->maskedQuoteIdToQuoteId->method('execute')
            ->willReturn(123);
        $this->cartRepository->method('getActive')
            ->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not find cart with ID "123". Please try again');

        $this->quoteRetriever->getQuoteByMaskedId('masked-cart-id');
    }

    public function testGetQuoteByMaskedIdThrowsWhenQuoteHasNoId(): void
    {
        $quote = $this->createQuote(null, ['item']);

        $this->maskedQuoteIdToQuoteId->method('execute')
            ->willReturn(123);
        $this->cartRepository->method('getActive')
            ->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not find cart with ID "". Please try again');

        $this->quoteRetriever->getQuoteByMaskedId('masked-cart-id');
    }

    /**
     * @param int|null $id
     * @param array $items
     * @return Quote|MockObject
     */
    private function createQuote(?int $id, array $items): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getAllItems'])
            ->getMock();
        $quote->method('getId')
            ->willReturn($id);
        $quote->method('getAllItems')
            ->willReturn($items);

        return $quote;
    }
}

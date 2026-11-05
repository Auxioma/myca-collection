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

namespace Magento\PaymentServicesPaypal\Test\Unit\Controller\SmartButtons;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesPaypal\Controller\SmartButtons\PlaceOrder;
use Magento\PaymentServicesPaypal\Model\CancellationService;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaceOrderTest extends TestCase
{
    /**
     * @var ResponseInterface|MockObject
     */
    private $response;

    /**
     * @var MessageManagerInterface|MockObject
     */
    private $messageManager;

    /**
     * @var Checkout|MockObject
     */
    private $checkout;

    /**
     * @var UrlInterface|MockObject
     */
    private $url;

    /**
     * @var CancellationService|MockObject
     */
    private $cancellationService;

    /**
     * @var QuoteStateManager|MockObject
     */
    private $quoteStateManager;

    /**
     * @var PlaceOrder
     */
    private PlaceOrder $controller;

    protected function setUp(): void
    {
        $this->response = $this->createMock(HttpResponse::class);
        $this->messageManager = $this->createMock(MessageManagerInterface::class);
        $this->checkout = $this->createMock(Checkout::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->cancellationService = $this->createMock(CancellationService::class);
        $this->quoteStateManager = $this->createMock(QuoteStateManager::class);
        $this->controller = new PlaceOrder(
            $this->createMock(RequestInterface::class),
            $this->response,
            $this->messageManager,
            $this->checkout,
            $this->url,
            $this->cancellationService,
            $this->quoteStateManager
        );
    }

    public function testLocalizedPlacementFailureRecoversBeforeCancellation(): void
    {
        $exception = new LocalizedException(__('Original payment failure'));
        $quote = $this->createQuote();
        $recovered = false;

        $this->checkout->method('getQuote')->willReturn($quote);
        $this->checkout->method('placeOrder')->willThrowException($exception);
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote)
            ->willReturnCallback(static function () use (&$recovered): void {
                $recovered = true;
            });
        $this->cancellationService->expects($this->once())->method('execute')->with(1)
            ->willReturnCallback(static function () use (&$recovered): bool {
                self::assertTrue($recovered);
                return true;
            });
        $this->messageManager->expects($this->once())->method('addExceptionMessage')
            ->with($exception, 'Original payment failure');
        $this->url->method('getUrl')->with('checkout/cart')->willReturn('/checkout/cart');
        $this->response->expects($this->once())->method('setRedirect')->with('/checkout/cart');

        $this->assertSame($this->response, $this->controller->execute());
    }

    public function testRecoveryFailureShowsRestoreErrorAndStillCancels(): void
    {
        $exception = new LocalizedException(__('Original payment failure'));
        $quote = $this->createQuote();
        $this->checkout->method('getQuote')->willReturn($quote);
        $this->checkout->method('placeOrder')->willThrowException($exception);
        $this->quoteStateManager->method('recover')
            ->willThrowException(new LocalizedException(__('Unable to restore the cart. Please try again.')));
        $this->cancellationService->expects($this->once())->method('execute')->with(1)->willReturn(false);
        $this->messageManager->expects($this->once())->method('addExceptionMessage')->with(
            $exception,
            'Unable to restore the cart. Please try again.'
        );
        $this->url->method('getUrl')->with('*/*/review')->willReturn('/review');
        $this->response->expects($this->once())->method('setRedirect')->with('/review');

        $this->assertSame($this->response, $this->controller->execute());
    }

    private function createQuote(): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $quote->method('getId')->willReturn(1);

        return $quote;
    }
}

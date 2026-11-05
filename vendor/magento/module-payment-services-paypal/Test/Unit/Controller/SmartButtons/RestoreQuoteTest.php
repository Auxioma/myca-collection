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

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\PaymentServicesPaypal\Controller\SmartButtons\RestoreQuote;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RestoreQuoteTest extends TestCase
{
    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSession;

    /**
     * @var QuoteStateManager|MockObject
     */
    private $quoteStateManager;

    /**
     * @var Json|MockObject
     */
    private $result;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /** @var RestoreQuote */
    private RestoreQuote $controller;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->quoteStateManager = $this->createMock(QuoteStateManager::class);
        $jsonFactory = $this->createMock(JsonFactory::class);
        $this->result = $this->createMock(Json::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $jsonFactory->method('create')->willReturn($this->result);
        $this->controller = new RestoreQuote(
            $this->checkoutSession,
            $this->quoteStateManager,
            $jsonFactory,
            $this->logger
        );
    }

    public function testControllerIsPostOnlyAndUsesStandardCsrfValidation(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->assertInstanceOf(HttpPostActionInterface::class, $this->controller);
        $this->assertNull($this->controller->validateForCsrf($request));
        $this->assertNull($this->controller->createCsrfValidationException($request));
    }

    public function testExecuteReturnsNoOpWithoutCheckoutQuote(): void
    {
        $this->checkoutSession->method('getQuoteId')->willReturn(null);
        $this->checkoutSession->expects($this->never())->method('getQuote');
        $this->quoteStateManager->expects($this->never())->method('restore');
        $this->result->expects($this->once())->method('setData')
            ->with(['success' => true, 'restored' => false])->willReturnSelf();

        $this->assertSame($this->result, $this->controller->execute());
    }

    public function testExecuteRestoresCheckoutQuote(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->checkoutSession->method('getQuoteId')->willReturn(1);
        $this->checkoutSession->method('getQuote')->willReturn($quote);
        $this->quoteStateManager->expects($this->once())->method('restore')->with($quote)->willReturn(true);
        $this->result->expects($this->once())->method('setData')
            ->with(['success' => true, 'restored' => true])->willReturnSelf();

        $this->assertSame($this->result, $this->controller->execute());
    }

    public function testExecuteReturnsSuccessfulNoOpWhenNothingWasRestored(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->checkoutSession->method('getQuoteId')->willReturn(1);
        $this->checkoutSession->method('getQuote')->willReturn($quote);
        $this->quoteStateManager->method('restore')->willReturn(false);
        $this->result->expects($this->once())->method('setData')
            ->with(['success' => true, 'restored' => false])->willReturnSelf();

        $this->assertSame($this->result, $this->controller->execute());
    }

    public function testExecuteReturnsFailureAndSanitizesLog(): void
    {
        $quote = $this->createMock(Quote::class);
        $exception = new RuntimeException('Sensitive quote data', 82);
        $this->checkoutSession->method('getQuoteId')->willReturn(1);
        $this->checkoutSession->method('getQuote')->willReturn($quote);
        $this->quoteStateManager->method('restore')->willThrowException($exception);
        $this->logger->expects($this->once())->method('error')->with(
            'Unable to restore quote after an aborted PayPal attempt.',
            ['exception_type' => RuntimeException::class, 'exception_code' => 82]
        );
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(500)->willReturnSelf();
        $this->result->expects($this->once())->method('setData')->with(['success' => false])->willReturnSelf();

        $this->assertSame($this->result, $this->controller->execute());
    }
}

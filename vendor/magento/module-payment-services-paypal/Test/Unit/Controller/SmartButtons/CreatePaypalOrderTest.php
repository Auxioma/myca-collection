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
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Controller\SmartButtons\CreatePaypalOrder;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CreatePaypalOrderTest extends TestCase
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var Json
     */
    private Json $result;

    /**
     * @var Checkout
     */
    private Checkout $checkout;

    /**
     * @var QuoteStateManager
     */
    private QuoteStateManager $quoteStateManager;

    /**
     * @var CreatePaypalOrder
     */
    private CreatePaypalOrder $controller;

    protected function setUp(): void
    {
        $this->request = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPost'])
            ->getMock();
        $resultFactory = $this->createMock(ResultFactory::class);
        $this->result = $this->createMock(Json::class);
        $this->checkout = $this->createMock(Checkout::class);
        $this->quoteStateManager = $this->createMock(QuoteStateManager::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($this->result);
        $this->controller = new CreatePaypalOrder(
            $this->request,
            $resultFactory,
            $this->checkout,
            $this->quoteStateManager
        );
    }

    public function testSuccessfulCreationReturnsSaasResponse(): void
    {
        $response = ['is_successful' => true, 'status' => 201];
        $this->configureRequest('cart');
        $this->checkout->expects($this->once())->method('setLocation')->with('cart');
        $this->checkout->expects($this->once())->method('unsetQuote');
        $this->checkout->expects($this->once())->method('validateQuote');
        $this->checkout->expects($this->once())->method('createPayPalOrder')
            ->with('paypal', null)->willReturn($response);
        $this->quoteStateManager->expects($this->never())->method('recover');
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(201)->willReturnSelf();
        $this->result->expects($this->once())->method('setData')
            ->with(['response' => $response])->willReturnSelf();

        self::assertSame($this->result, $this->controller->execute());
    }

    public function testValidationFailurePreservesLocalizedErrorWithoutRecovery(): void
    {
        $this->configureRequest('cart');
        $this->checkout->method('setLocation');
        $this->checkout->method('unsetQuote');
        $this->checkout->method('validateQuote')
            ->willThrowException(new LocalizedException(__('Invalid quote details')));
        $this->checkout->expects($this->never())->method('createPayPalOrder');
        $this->quoteStateManager->expects($this->never())->method('recover');
        $this->expectErrorResponse('Invalid quote details');

        self::assertSame($this->result, $this->controller->execute());
    }

    public function testUnexpectedValidationFailureReturnsGenericErrorWithoutRecovery(): void
    {
        $this->configureRequest('cart');
        $this->checkout->method('setLocation');
        $this->checkout->method('unsetQuote');
        $this->checkout->method('validateQuote')->willThrowException(new RuntimeException('Sensitive failure'));
        $this->checkout->expects($this->never())->method('createPayPalOrder');
        $this->quoteStateManager->expects($this->never())->method('recover');
        $this->expectErrorResponse('Can\'t create PayPal order. Please try again.');

        self::assertSame($this->result, $this->controller->execute());
    }

    public function testCreationFailureRecoversNonPdpQuote(): void
    {
        $quote = $this->createMock(Quote::class);
        $recovered = false;
        $this->configureRequest('cart');
        $this->checkout->method('setLocation');
        $this->checkout->method('unsetQuote');
        $this->checkout->method('validateQuote');
        $this->checkout->method('createPayPalOrder')->willThrowException(new RuntimeException('SaaS failed'));
        $this->checkout->method('getQuote')->willReturn($quote);
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote)
            ->willReturnCallback(static function () use (&$recovered): void {
                $recovered = true;
            });
        $result = $this->result;
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(500)
            ->willReturnCallback(static function () use (&$recovered, $result): Json {
                self::assertTrue($recovered);
                return $result;
            });
        $this->result->method('setData')->willReturnSelf();

        self::assertSame($this->result, $this->controller->execute());
    }

    public function testRecoveryFailureStillReturnsGenericError(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->configureRequest('cart');
        $this->checkout->method('setLocation');
        $this->checkout->method('unsetQuote');
        $this->checkout->method('validateQuote');
        $this->checkout->method('createPayPalOrder')->willThrowException(new RuntimeException('SaaS failed'));
        $this->checkout->method('getQuote')->willReturn($quote);
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote)
            ->willThrowException(new LocalizedException(__('Unable to restore the cart. Please try again.')));
        $this->expectErrorResponse('Can\'t create PayPal order. Please try again.');

        self::assertSame($this->result, $this->controller->execute());
    }

    public function testCreationFailureDoesNotRecoverPdpShadowQuote(): void
    {
        $this->configureRequest(Checkout::LOCATION_PRODUCT_PAGE);
        $this->checkout->method('setLocation');
        $this->checkout->expects($this->never())->method('unsetQuote');
        $this->checkout->method('validateQuote');
        $this->checkout->method('createPayPalOrder')->willThrowException(new RuntimeException('SaaS failed'));
        $this->checkout->expects($this->never())->method('getQuote');
        $this->quoteStateManager->expects($this->never())->method('recover');
        $this->expectErrorResponse('Can\'t create PayPal order. Please try again.');

        self::assertSame($this->result, $this->controller->execute());
    }

    private function configureRequest(string $location): void
    {
        $this->request->method('getPost')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'payment_source' => 'paypal',
                'three_ds_mode' => null,
                'location' => $location,
                default => null
            }
        );
    }

    private function expectErrorResponse(string $message): void
    {
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(500)->willReturnSelf();
        $this->result->expects($this->once())->method('setData')->with([
            'response' => ['error' => $message]
        ])->willReturnSelf();
    }
}

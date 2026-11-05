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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesPaypal\Model\Api\ShippingCallbackManagement;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\AddressValidator;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteRetriever;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\ResponseBuilder;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\ShippingProcessor;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShippingCallbackManagementTest extends TestCase
{
    /**
     * @var Json|MockObject
     */
    private $json;

    /**
     * @var QuoteRetriever|MockObject
     */
    private $quoteRetriever;

    /**
     * @var AddressValidator|MockObject
     */
    private $addressValidator;

    /**
     * @var ShippingProcessor|MockObject
     */
    private $shippingProcessor;

    /**
     * @var ResponseBuilder|MockObject
     */
    private $responseBuilder;

    /**
     * @var QuoteStateManager|MockObject
     */
    private $quoteStateManager;

    /** @var ShippingCallbackManagement */
    private ShippingCallbackManagement $management;

    protected function setUp(): void
    {
        $this->json = $this->createMock(Json::class);
        $this->quoteRetriever = $this->createMock(QuoteRetriever::class);
        $this->addressValidator = $this->createMock(AddressValidator::class);
        $this->shippingProcessor = $this->createMock(ShippingProcessor::class);
        $this->responseBuilder = $this->createMock(ResponseBuilder::class);
        $this->quoteStateManager = $this->createMock(QuoteStateManager::class);
        $this->management = new ShippingCallbackManagement(
            $this->json,
            $this->quoteRetriever,
            $this->addressValidator,
            $this->shippingProcessor,
            $this->responseBuilder,
            $this->quoteStateManager
        );
    }

    public function testValidAddressCallbackCapturesBeforeMutation(): void
    {
        $address = ['country_code' => 'US', 'admin_area_1' => 'CA'];
        $requestData = ['id' => 'PAYPAL-ORDER-ID', 'shipping_address' => $address];
        $quote = $this->createQuote('SESSION-ID', 'PAYPAL-ORDER-ID');
        $captured = false;

        $this->json->method('unserialize')->willReturn($requestData);
        $this->quoteRetriever->method('getQuoteByMaskedId')->with('MASKED-ID')->willReturn($quote);
        $this->quoteStateManager->expects($this->once())->method('capture')->with($quote)
            ->willReturnCallback(static function () use (&$captured): void {
                $captured = true;
            });
        $this->addressValidator->expects($this->once())->method('validateAndSetAddress')
            ->with($quote, $address)
            ->willReturnCallback(static function () use (&$captured): void {
                self::assertTrue($captured);
            });
        $this->shippingProcessor->expects($this->never())->method('processShippingOptions');
        $this->responseBuilder->expects($this->once())->method('buildResponse')
            ->with($quote, $requestData)->willReturn(['status' => 'ok']);

        $this->assertSame(
            ['status' => 'ok'],
            $this->management->execute('MASKED-ID', 'SESSION-ID', '{}')
        );
    }

    public function testValidShippingOptionCallbackCapturesBeforeMutation(): void
    {
        $option = ['id' => 'flatrate|flatrate'];
        $requestData = ['id' => 'PAYPAL-ORDER-ID', 'shipping_option' => $option];
        $quote = $this->createQuote('SESSION-ID', 'PAYPAL-ORDER-ID');
        $captured = false;

        $this->json->method('unserialize')->willReturn($requestData);
        $this->quoteRetriever->method('getQuoteByMaskedId')->willReturn($quote);
        $this->quoteStateManager->expects($this->once())->method('capture')
            ->willReturnCallback(static function () use (&$captured): void {
                $captured = true;
            });
        $this->addressValidator->expects($this->never())->method('validateAndSetAddress');
        $this->shippingProcessor->expects($this->once())->method('processShippingOptions')
            ->with($quote, $option)
            ->willReturnCallback(static function () use (&$captured): void {
                self::assertTrue($captured);
            });
        $this->responseBuilder->method('buildResponse')->willReturn([]);

        $this->assertSame([], $this->management->execute('MASKED-ID', 'SESSION-ID', '{}'));
    }

    public function testInvalidSessionIsRejectedBeforeCaptureOrMutation(): void
    {
        $this->configureRejectedCallback('WRONG-SESSION', 'PAYPAL-ORDER-ID');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to process shipping callback');

        $this->management->execute('MASKED-ID', 'SESSION-ID', '{}');
    }

    public function testInvalidPayPalOrderIsRejectedBeforeCaptureOrMutation(): void
    {
        $this->configureRejectedCallback('SESSION-ID', 'OTHER-ORDER');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to process shipping callback');

        $this->management->execute('MASKED-ID', 'SESSION-ID', '{}');
    }

    public function testCallbackWithoutAddressOrOptionDoesNotCapture(): void
    {
        $requestData = ['id' => 'PAYPAL-ORDER-ID'];
        $quote = $this->createQuote('SESSION-ID', 'PAYPAL-ORDER-ID');
        $this->json->method('unserialize')->willReturn($requestData);
        $this->quoteRetriever->method('getQuoteByMaskedId')->willReturn($quote);
        $this->quoteStateManager->expects($this->never())->method('capture');
        $this->addressValidator->expects($this->never())->method('validateAndSetAddress');
        $this->shippingProcessor->expects($this->never())->method('processShippingOptions');

        $this->assertSame([], $this->management->execute('MASKED-ID', 'SESSION-ID', '{}'));
    }

    public function testMissingPayPalOrderIdIsRejectedBeforeQuoteLoad(): void
    {
        $this->json->method('unserialize')->willReturn(['shipping_address' => ['country_code' => 'US']]);
        $this->quoteRetriever->expects($this->never())->method('getQuoteByMaskedId');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Missing PayPal order ID');

        $this->management->execute('MASKED-ID', 'SESSION-ID', '{}');
    }

    private function configureRejectedCallback(string $storedSessionId, string $storedPayPalOrderId): void
    {
        $this->json->method('unserialize')->willReturn([
            'id' => 'PAYPAL-ORDER-ID',
            'shipping_address' => ['country_code' => 'US']
        ]);
        $this->quoteRetriever->method('getQuoteByMaskedId')
            ->willReturn($this->createQuote($storedSessionId, $storedPayPalOrderId));
        $this->quoteStateManager->expects($this->never())->method('capture');
        $this->addressValidator->expects($this->never())->method('validateAndSetAddress');
        $this->shippingProcessor->expects($this->never())->method('processShippingOptions');
        $this->responseBuilder->expects($this->never())->method('buildResponse');
    }

    private function createQuote(string $sessionId, string $paypalOrderId): Quote&MockObject
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => match ($field) {
                QuoteStateManager::SESSION_ID_KEY => $sessionId,
                'paypal_order_id' => $paypalOrderId,
                default => null
            }
        );
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);

        return $quote;
    }
}

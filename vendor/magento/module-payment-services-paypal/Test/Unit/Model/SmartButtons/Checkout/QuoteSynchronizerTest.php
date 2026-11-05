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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\SmartButtons\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\AddressConverter;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\QuoteSynchronizer;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

class QuoteSynchronizerTest extends TestCase
{
    /**
     * @var OrderService|MockObject
     */
    private $orderService;

    /**
     * @var AddressConverter|MockObject
     */
    private $addressConverter;

    /**
     * @var QuoteStateManager|MockObject
     */
    private $quoteStateManager;

    /**
     * @var QuoteSynchronizer
     */
    private QuoteSynchronizer $quoteSynchronizer;

    protected function setUp(): void
    {
        $this->orderService = $this->createMock(OrderService::class);
        $this->addressConverter = $this->createMock(AddressConverter::class);
        $this->quoteStateManager = $this->createMock(QuoteStateManager::class);
        $this->quoteSynchronizer = new QuoteSynchronizer(
            $this->orderService,
            $this->addressConverter,
            $this->quoteStateManager
        );
    }

    public function testSynchronizeUpdatesQuoteFromPayPalOrder(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $order = [
            'paypal-order' => [
                'payer' => [
                    'email' => 'buyer@example.com',
                    'payer_id' => 'PAYER-ID'
                ]
            ]
        ];
        $shippingAddressData = ['firstname' => 'Jane', 'lastname' => 'Doe'];
        $billingAddressData = ['firstname' => 'John', 'lastname' => 'Doe'];

        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('paypal_order_id')
            ->willReturn($paypalOrderId);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('paypal_payer_id', 'PAYER-ID')
            ->willReturnSelf();

        $shippingAddress = $this->createAddress();
        $billingAddress = $this->createAddress();
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getStoreId', 'getShippingAddress', 'getBillingAddress'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        $this->orderService->expects($this->once())
            ->method('get')
            ->with('1', $paypalOrderId)
            ->willReturn($order);
        $this->addressConverter->expects($this->once())
            ->method('convertShippingAddress')
            ->with($order)
            ->willReturn($shippingAddressData);
        $this->addressConverter->expects($this->once())
            ->method('convertBillingAddress')
            ->with($order)
            ->willReturn($billingAddressData);
        $this->quoteStateManager->expects($this->once())->method('discard')->with($quote);

        $this->quoteSynchronizer->synchronize($quote);

        $this->assertSame('buyer@example.com', $quote->getCustomerEmail());
        $this->assertSame('Jane', $shippingAddress->getFirstname());
        $this->assertSame('Doe', $shippingAddress->getLastname());
        $this->assertTrue($shippingAddress->getCollectShippingRates());
        $this->assertSame('John', $billingAddress->getFirstname());
        $this->assertSame('Doe', $billingAddress->getLastname());
    }

    public function testSynchronizePreservesExistingCustomerEmail(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $order = [
            'paypal-order' => [
                'payer' => [
                    'email' => 'paypal-buyer@example.com'
                ]
            ]
        ];
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')
            ->with('paypal_order_id')
            ->willReturn($paypalOrderId);
        $payment->expects($this->never())->method('setAdditionalInformation');
        $shippingAddress = $this->createAddress();
        $billingAddress = $this->createAddress();
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getStoreId', 'getShippingAddress', 'getBillingAddress'])
            ->getMock();
        $quote->setCustomerEmail('customer@example.com');
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        $this->orderService->expects($this->once())
            ->method('get')
            ->with('1', $paypalOrderId)
            ->willReturn($order);
        $this->addressConverter->expects($this->once())
            ->method('convertShippingAddress')
            ->with($order)
            ->willReturn(['firstname' => 'Jane', 'lastname' => 'Doe']);
        $this->addressConverter->expects($this->once())
            ->method('convertBillingAddress')
            ->with($order)
            ->willReturn(['firstname' => 'John', 'lastname' => 'Doe']);
        $this->quoteStateManager->expects($this->once())->method('discard')->with($quote);

        $this->quoteSynchronizer->synchronize($quote);

        $this->assertSame('customer@example.com', $quote->getCustomerEmail());
        $this->assertSame('Jane', $shippingAddress->getFirstname());
        $this->assertSame('Doe', $shippingAddress->getLastname());
        $this->assertSame('John', $billingAddress->getFirstname());
        $this->assertSame('Doe', $billingAddress->getLastname());
    }

    public function testSynchronizeRejectsQuoteWithoutPayPalOrderId(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('paypal_order_id')
            ->willReturn(null);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);

        $this->orderService->expects($this->never())->method('get');
        $this->addressConverter->expects($this->never())->method('convertShippingAddress');
        $this->addressConverter->expects($this->never())->method('convertBillingAddress');
        $this->quoteStateManager->expects($this->never())->method('discard');
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('PayPal order not found. Please try again.');

        $this->quoteSynchronizer->synchronize($quote);
    }

    public function testSynchronizeWithoutPayerIdDoesNotSetPaymentMetadata(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $order = [
            'paypal-order' => [
                'payer' => [
                    'email' => 'buyer@example.com'
                ]
            ]
        ];

        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('paypal_order_id')
            ->willReturn($paypalOrderId);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getStoreId', 'getShippingAddress', 'getBillingAddress'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($this->createAddress());
        $quote->method('getBillingAddress')->willReturn($this->createAddress());

        $this->orderService->expects($this->once())
            ->method('get')
            ->with('1', $paypalOrderId)
            ->willReturn($order);
        $this->addressConverter->expects($this->once())
            ->method('convertShippingAddress')
            ->with($order)
            ->willReturn([]);
        $this->addressConverter->expects($this->once())
            ->method('convertBillingAddress')
            ->with($order)
            ->willReturn([]);
        $this->quoteStateManager->expects($this->once())->method('discard')->with($quote);

        $this->quoteSynchronizer->synchronize($quote);

        $this->assertSame('buyer@example.com', $quote->getCustomerEmail());
    }

    public function testSynchronizeRecoversQuoteWhenPayPalRequestFails(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $exception = new LocalizedException(__('Unable to retrieve PayPal order.'));
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->with('paypal_order_id')->willReturn($paypalOrderId);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getStoreId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);

        $this->orderService->expects($this->once())->method('get')->willThrowException($exception);
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);
        $this->quoteStateManager->expects($this->never())->method('discard');

        $this->expectExceptionObject($exception);

        $this->quoteSynchronizer->synchronize($quote);
    }

    public function testSynchronizeRecoversQuoteWhenAddressConversionFails(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $order = ['paypal-order' => ['payer' => []]];
        $exception = new RuntimeException('Unable to convert PayPal address.');
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->with('paypal_order_id')->willReturn($paypalOrderId);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getStoreId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);

        $this->orderService->expects($this->once())->method('get')->willReturn($order);
        $this->addressConverter->expects($this->once())
            ->method('convertShippingAddress')
            ->with($order)
            ->willThrowException($exception);
        $this->addressConverter->expects($this->never())->method('convertBillingAddress');
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);
        $this->quoteStateManager->expects($this->never())->method('discard');

        $this->expectExceptionObject($exception);

        $this->quoteSynchronizer->synchronize($quote);
    }

    public function testSynchronizeWrapsNonExceptionThrowableAfterRecovery(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $throwable = new TypeError('Invalid PayPal order response.');
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->with('paypal_order_id')->willReturn($paypalOrderId);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getStoreId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);

        $this->orderService->expects($this->once())->method('get')->willThrowException($throwable);
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);
        $this->quoteStateManager->expects($this->never())->method('discard');

        try {
            $this->quoteSynchronizer->synchronize($quote);
            $this->fail('Expected synchronization to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to synchronize PayPal order.', $exception->getMessage());
            $this->assertSame($throwable, $exception->getPrevious());
        }
    }

    private function createAddress(): Address
    {
        return $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}

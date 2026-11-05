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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardBinDetailsInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentCardDetailsInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderDetailsInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderInterface;
use Magento\PaymentServicesPaypal\Api\Data\PaymentOrderInterfaceFactory;
use Magento\PaymentServicesPaypal\Api\Data\PaymentSourceDetailsInterfaceFactory;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\Api\Data\PaymentCardAuthenticationResultDetailsFactory;
use Magento\PaymentServicesPaypal\Model\ApplePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\PaymentOrderManagement;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\AddressConverter;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentOrderManagementTest extends TestCase
{
    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $quoteRepository;

    /**
     * @var OrderService|MockObject
     */
    private $orderService;

    /**
     * @var OrderHelper|MockObject
     */
    private $orderHelper;

    /**
     * @var AddressConverter|MockObject
     */
    private $addressConverter;

    /**
     * @var QuoteStateManager|MockObject
     */
    private $quoteStateManager;

    protected function setUp(): void
    {
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->orderService = $this->createMock(OrderService::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->addressConverter = $this->createMock(AddressConverter::class);
        $this->quoteStateManager = $this->createMock(QuoteStateManager::class);
    }

    /**
     * @dataProvider addressMappingProvider
     */
    public function testAddressMappingDependsOnSsscExpressFlow(
        string $location,
        string $paymentSource,
        bool $mapsAddresses,
        bool $preparesRecoverableState,
        bool $isVirtual = false
    ): void {
        $quote = $this->createQuote($isVirtual);
        $this->configureCreateDependencies($quote, $location);

        $statePrepared = false;
        $this->quoteStateManager->expects($preparesRecoverableState ? $this->once() : $this->never())
            ->method('prepareForNewAttempt')->with($quote)
            ->willReturnCallback(static function () use (&$statePrepared): void {
                $statePrepared = true;
            });
        $this->orderService->expects($this->exactly($mapsAddresses ? 2 : 0))->method('mapAddress')
            ->willReturnCallback(
                static function () use (&$statePrepared, $preparesRecoverableState): array {
                    self::assertSame($preparesRecoverableState, $statePrepared);
                    return [];
                }
            );
        $this->orderService->expects($this->once())->method('create')
            ->with($this->anything(), $this->callback(
                static function (array $data) use ($mapsAddresses, &$statePrepared, $preparesRecoverableState): bool {
                    self::assertSame($preparesRecoverableState, $statePrepared);
                    return array_key_exists('shipping_address', $data) === $mapsAddresses
                        && array_key_exists('billing_address', $data) === $mapsAddresses;
                }
            ))
            ->willReturn([
                'paypal-order' => ['id' => 'PAYPAL-ID', 'mp_order_id' => 'MP-ID', 'status' => 'CREATED']
            ]);

        $this->createManagement()->create(
            'payment_services_paypal_smart_buttons',
            $paymentSource,
            1,
            $location
        );
    }

    public function testCreationFailureRecoversSsscQuoteAndRethrowsOriginalFailure(): void
    {
        $quote = $this->createQuote(false);
        $this->configureCreateDependencies($quote, 'CART');
        $exception = new RuntimeException('SaaS failed');
        $this->orderService->method('create')->willThrowException($exception);
        $this->quoteStateManager->expects($this->once())->method('prepareForNewAttempt')->with($quote);
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);

        $this->expectExceptionObject($exception);

        $this->createManagement()->create(
            'payment_services_paypal_smart_buttons',
            'paypal',
            1,
            'CART'
        );
    }

    public function testUnsuccessfulCreationResponseRecoversActiveSsscAttempt(): void
    {
        $quote = $this->createQuote(false);
        $this->configureCreateDependencies($quote, 'CART');
        $this->orderService->method('create')->willReturn(['message' => 'Unable to create order']);
        $this->quoteStateManager->expects($this->once())->method('prepareForNewAttempt')->with($quote);
        $this->quoteStateManager->expects($this->once())->method('hasActiveSsscAttempt')
            ->with($quote)->willReturn(true);
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);
        $this->quoteRepository->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to create an order: Unable to create order');

        $this->createManagement()->create(
            'payment_services_paypal_smart_buttons',
            'paypal',
            1,
            'CART'
        );
    }

    public function testSyncUpdatesQuoteAndDiscardsRecoveryStateBeforeSave(): void
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
        $payment->method('getAdditionalInformation')->with('paypal_order_id')->willReturn($paypalOrderId);
        $payment->expects($this->once())->method('setAdditionalInformation')
            ->with('paypal_payer_id', 'PAYER-ID')->willReturnSelf();
        $shippingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addData'])
            ->addMethods(['setCollectShippingRates'])
            ->getMock();
        $billingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addData'])
            ->getMock();
        $quote = $this->createSyncQuote($payment, $shippingAddress, $billingAddress);
        $this->quoteRepository->method('getActive')->with(1)->willReturn($quote);
        $this->orderService->expects($this->once())->method('get')
            ->with('1', $paypalOrderId)->willReturn($order);
        $this->addressConverter->expects($this->once())->method('convertShippingAddress')
            ->with($order)->willReturn($shippingAddressData);
        $this->addressConverter->expects($this->once())->method('convertBillingAddress')
            ->with($order)->willReturn($billingAddressData);
        $shippingAddress->expects($this->once())->method('addData')
            ->with($shippingAddressData)->willReturnSelf();
        $shippingAddress->expects($this->once())->method('setCollectShippingRates')
            ->with(true)->willReturnSelf();
        $billingAddress->expects($this->once())->method('addData')
            ->with($billingAddressData)->willReturnSelf();

        $recoveryStateDiscarded = false;
        $this->quoteStateManager->expects($this->once())->method('discard')->with($quote)
            ->willReturnCallback(static function () use (&$recoveryStateDiscarded): void {
                $recoveryStateDiscarded = true;
            });
        $this->quoteStateManager->expects($this->never())->method('recover');
        $quote->expects($this->once())->method('collectTotals')
            ->willReturnCallback(static function () use (&$recoveryStateDiscarded, $quote): Quote {
                self::assertTrue($recoveryStateDiscarded);
                return $quote;
            });
        $this->quoteRepository->expects($this->once())->method('save')->with($quote)
            ->willReturnCallback(static function () use (&$recoveryStateDiscarded, $quote): Quote {
                self::assertTrue($recoveryStateDiscarded);
                return $quote;
            });

        $this->assertTrue($this->createManagement()->sync(1, $paypalOrderId));
        $this->assertSame('buyer@example.com', $quote->getCustomerEmail());
    }

    public function testSyncRecoversAndRethrowsPayPalLookupFailure(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $exception = new RuntimeException('Unable to retrieve PayPal order.');
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->with('paypal_order_id')->willReturn($paypalOrderId);
        $quote = $this->createSyncQuote($payment);
        $this->quoteRepository->method('getActive')->with(1)->willReturn($quote);
        $this->orderService->expects($this->once())->method('get')->willThrowException($exception);
        $this->addressConverter->expects($this->never())->method('convertShippingAddress');
        $this->addressConverter->expects($this->never())->method('convertBillingAddress');
        $this->quoteStateManager->expects($this->never())->method('discard');
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);
        $quote->expects($this->never())->method('collectTotals');
        $this->quoteRepository->expects($this->never())->method('save');

        $this->expectExceptionObject($exception);

        $this->createManagement()->sync(1, $paypalOrderId);
    }

    public function testSyncRecoversAndRethrowsAddressConversionFailure(): void
    {
        $paypalOrderId = 'PAYPAL-ORDER-ID';
        $order = ['paypal-order' => ['payer' => []]];
        $exception = new RuntimeException('Unable to convert PayPal billing address.');
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->with('paypal_order_id')->willReturn($paypalOrderId);
        $payment->expects($this->never())->method('setAdditionalInformation');
        $shippingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addData'])
            ->addMethods(['setCollectShippingRates'])
            ->getMock();
        $billingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addData'])
            ->getMock();
        $quote = $this->createSyncQuote($payment, $shippingAddress, $billingAddress);
        $this->quoteRepository->method('getActive')->with(1)->willReturn($quote);
        $this->orderService->expects($this->once())->method('get')->willReturn($order);
        $this->addressConverter->expects($this->once())->method('convertShippingAddress')
            ->with($order)->willReturn(['firstname' => 'Jane']);
        $shippingAddress->expects($this->once())->method('addData')
            ->with(['firstname' => 'Jane'])->willReturnSelf();
        $shippingAddress->expects($this->once())->method('setCollectShippingRates')
            ->with(true)->willReturnSelf();
        $this->addressConverter->expects($this->once())->method('convertBillingAddress')
            ->with($order)->willThrowException($exception);
        $billingAddress->expects($this->never())->method('addData');
        $this->quoteStateManager->expects($this->never())->method('discard');
        $this->quoteStateManager->expects($this->once())->method('recover')->with($quote);
        $quote->expects($this->never())->method('collectTotals');
        $this->quoteRepository->expects($this->never())->method('save');

        $this->expectExceptionObject($exception);

        $this->createManagement()->sync(1, $paypalOrderId);
    }

    public static function addressMappingProvider(): array
    {
        $cases = [];
        foreach ([
            ['CART', false, true],
            ['MINICART', false, true],
            ['START_OF_CHECKOUT', false, true],
            ['PRODUCT_DETAIL', false, false],
            ['CHECKOUT', true, false]
        ] as [$location, $mapsAddresses, $shouldPrepare]) {
            foreach (['paypal', 'paylater', 'venmo'] as $source) {
                $cases[strtolower($location . '-' . $source)] = [$location, $source, $mapsAddresses, $shouldPrepare];
            }
            foreach (['googlepay', ApplePayConfigProvider::PAYMENT_SOURCE, 'card', 'ideal', 'unknown', ''] as $source) {
                $cases[strtolower($location . '-excluded-' . $source)] = [$location, $source, true, false];
            }
        }
        $cases['virtual-sssc-express'] = ['CART', 'paypal', false, true, true];

        return $cases;
    }

    private function configureCreateDependencies(Quote $quote, string $location): void
    {
        $this->quoteRepository->method('getActive')->with(1)->willReturn($quote);
        $this->orderHelper->method('validateCheckoutLocation')->willReturn($location);
        $this->orderHelper->method('reserveAndGetOrderIncrementId')->willReturn('000000001');
        $this->orderHelper->method('formatAmount')->willReturn('10.00');
        $this->orderHelper->method('getL2Data')->willReturn([]);
        $this->orderHelper->method('getL3Data')->willReturn([]);
        $this->orderHelper->method('getLineItems')->willReturn([]);
        $this->orderHelper->method('getAmountBreakdown')->willReturn([]);
        $this->orderHelper->method('getShippingPreference')->willReturn('NO_SHIPPING');
        $this->orderHelper->method('getUserAction')->willReturn('PAY_NOW');
        $this->orderHelper->method('isAppSwitchEnabled')->willReturn(false);
        $this->orderHelper->method('isContactPreferenceEnabled')->willReturn(false);
        $this->orderService->method('buildGuestPayer')->willReturn(['email' => 'buyer@example.com']);
    }

    private function createQuote(bool $isVirtual): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $payment = $this->getMockBuilder(Payment::class)->disableOriginalConstructor()->getMock();
        $currency = $this->createMock(CurrencyInterface::class);
        $store = $this->createMock(StoreInterface::class);
        $address = $this->getMockBuilder(Address::class)->disableOriginalConstructor()->getMock();
        $payment->method('setAdditionalInformation')->willReturnSelf();
        $payment->method('setMethod')->willReturnSelf();
        $currency->method('getBaseCurrencyCode')->willReturn('USD');
        $quote->method('getId')->willReturn(1);
        $quote->method('getAllItems')->willReturn([new \stdClass()]);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getCurrency')->willReturn($currency);
        $quote->method('getStore')->willReturn($store);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getBillingAddress')->willReturn($address);
        $quote->setData('store_id', 1);
        $quote->setData('base_grand_total', 10.0);

        return $quote;
    }

    private function createSyncQuote(
        Payment $payment,
        ?Address $shippingAddress = null,
        ?Address $billingAddress = null
    ): Quote {
        $shippingAddress = $shippingAddress
            ?: $this->getMockBuilder(Address::class)->disableOriginalConstructor()->getMock();
        $billingAddress = $billingAddress
            ?: $this->getMockBuilder(Address::class)->disableOriginalConstructor()->getMock();
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getPayment',
                'getStoreId',
                'getShippingAddress',
                'getBillingAddress',
                'collectTotals'
            ])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        return $quote;
    }

    private function createManagement(): PaymentOrderManagement
    {
        $response = $this->createMock(PaymentOrderInterface::class);
        foreach (['setId', 'setMpOrderId', 'setStatus', 'setAmount', 'setCurrencyCode'] as $method) {
            $response->method($method)->willReturnSelf();
        }
        $factory = $this->createMock(PaymentOrderInterfaceFactory::class);
        $factory->method('create')->willReturn($response);

        return new PaymentOrderManagement(
            $this->quoteRepository,
            $this->orderService,
            $factory,
            $this->createMock(PaymentOrderDetailsInterfaceFactory::class),
            $this->createMock(PaymentSourceDetailsInterfaceFactory::class),
            $this->createMock(PaymentCardDetailsInterfaceFactory::class),
            $this->createMock(PaymentCardBinDetailsInterfaceFactory::class),
            ['payment_services_paypal_smart_buttons'],
            $this->orderHelper,
            $this->addressConverter,
            $this->createMock(BaseConfig::class),
            $this->createMock(PaymentCardAuthenticationResultDetailsFactory::class),
            $this->quoteStateManager
        );
    }
}

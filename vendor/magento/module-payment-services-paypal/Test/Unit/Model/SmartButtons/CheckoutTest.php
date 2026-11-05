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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\SmartButtons;

use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Session\Generic as PaypalSession;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\ApplePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\Config as PayPalConfig;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout as SmartButtonsCheckout;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CheckoutTest extends TestCase
{
    /**
     * @dataProvider createOrderLocationProvider
     */
    public function testCreateOrderPreparesAbandonedStateOnlyForNonPdpSsscExpress(
        string $rawLocation,
        string $normalizedLocation,
        string $paymentSource,
        bool $shouldPrepare
    ): void {
        $quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $paypalSession = $this->getMockBuilder(PaypalSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getQuoteId', 'getLocation'])
            ->getMock();
        $orderHelper = $this->createMock(OrderHelper::class);
        $orderService = $this->createMock(OrderService::class);
        $quoteStateManager = $this->createMock(QuoteStateManager::class);
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $payment = $this->createMock(Payment::class);
        $currency = $this->createMock(CurrencyInterface::class);
        $store = $this->createMock(StoreInterface::class);

        $checkoutSession->method('getQuote')->willReturn($quote);
        $paypalSession->method('getQuoteId')->willReturn(null);
        $paypalSession->method('getLocation')->willReturn($rawLocation);
        $quote->method('getId')->willReturn(1);
        $quote->method('getAllItems')->willReturn([new \stdClass()]);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getCurrency')->willReturn($currency);
        $quote->method('getReservedOrderId')->willReturn('000000001');
        $quote->method('getStore')->willReturn($store);
        $quote->setData('base_grand_total', 10.0);
        $quote->setData('is_virtual', false);
        $quote->setData('reserved_order_id', '000000001');
        $quote->setData('store_id', 1);
        $currency->method('getBaseCurrencyCode')->willReturn('USD');
        $orderHelper->expects($this->once())->method('validateCheckoutLocation')
            ->with($rawLocation)->willReturn($normalizedLocation);
        $orderHelper->method('formatAmount')->willReturn('10.00');
        $orderHelper->method('reserveAndGetOrderIncrementId')->willReturn('000000001');
        $orderHelper->method('getLineItems')->willReturn([]);
        $orderHelper->method('getAmountBreakdown')->willReturn([]);
        $orderHelper->method('isPayPalSkipReviewEnabled')->willReturn(false);
        $orderHelper->method('getShippingPreference')->willReturn('NO_SHIPPING');
        $orderHelper->method('isAppSwitchEnabled')->willReturn(false);
        $orderHelper->method('isContactPreferenceEnabled')->willReturn(false);

        $prepared = false;
        $quoteStateManager->expects($shouldPrepare ? $this->once() : $this->never())
            ->method('prepareForNewAttempt')->with($quote)
            ->willReturnCallback(static function () use (&$prepared): void {
                $prepared = true;
            });
        $orderService->expects($this->once())->method('create')
            ->with($store, $this->callback(
                static function (array $data) use (&$prepared, $shouldPrepare, $normalizedLocation): bool {
                    self::assertSame($shouldPrepare, $prepared);
                    self::assertSame($normalizedLocation, $data['location']);
                    return true;
                }
            ))
            ->willReturn(['is_successful' => false, 'status' => 500]);
        $quoteRepository->expects($this->once())->method('save')->with($quote);

        $checkout = new SmartButtonsCheckout(
            $quoteRepository,
            $this->createMock(CartManagementInterface::class),
            $this->createMock(Data::class),
            $this->createMock(CustomerSession::class),
            $checkoutSession,
            $paypalSession,
            $this->createMock(OrderSender::class),
            $orderService,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Config::class),
            $this->createMock(Data::class),
            $orderHelper,
            $quoteStateManager
        );

        $checkout->createPayPalOrder($paymentSource);
    }

    public static function createOrderLocationProvider(): array
    {
        $cases = [
            'raw product location' => [
                SmartButtonsCheckout::LOCATION_PRODUCT_PAGE,
                PayPalConfig::PRODUCT_DETAIL_CHECKOUT_LOCATION,
                'paypal',
                false
            ],
            'canonical product detail location' => [
                PayPalConfig::PRODUCT_DETAIL_CHECKOUT_LOCATION,
                PayPalConfig::PRODUCT_DETAIL_CHECKOUT_LOCATION,
                'paypal',
                false
            ]
        ];

        foreach ([
            [PayPalConfig::CART_CHECKOUT_LOCATION, true],
            [PayPalConfig::MINICART_CHECKOUT_LOCATION, true],
            [PayPalConfig::START_OF_CHECKOUT_LOCATION, true],
            [PayPalConfig::PRODUCT_DETAIL_CHECKOUT_LOCATION, false],
            [PayPalConfig::CHECKOUT_CHECKOUT_LOCATION, false]
        ] as [$location, $shouldPrepare]) {
            foreach (['paypal', 'paylater', 'venmo'] as $source) {
                $cases[strtolower($location . '-' . $source)] = [
                    strtolower($location), $location, $source, $shouldPrepare
                ];
            }
            foreach (['googlepay', ApplePayConfigProvider::PAYMENT_SOURCE, 'card', 'ideal', 'unknown', ''] as $source) {
                $cases[strtolower($location . '-excluded-' . $source)] = [
                    strtolower($location), $location, $source, false
                ];
            }
        }

        return $cases;
    }

    public function testUpdateQuoteDiscardsSnapshotAfterSynchronizingApprovedAddress(): void
    {
        $quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $paypalSession = $this->getMockBuilder(PaypalSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getQuoteId'])
            ->getMock();
        $config = $this->createMock(Config::class);
        $orderHelper = $this->createMock(OrderHelper::class);
        $quoteStateManager = $this->createMock(QuoteStateManager::class);
        $payment = $this->createMock(Payment::class);
        $shippingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addData'])
            ->addMethods(['setCollectShippingRates'])
            ->getMock();
        $billingAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addData'])
            ->getMock();
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'getPayment', 'collectTotals'])
            ->getMock();

        $paypalSession->method('getQuoteId')->willReturn(null);
        $checkoutSession->method('getQuote')->willReturn($quote);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getPayment')->willReturn($payment);
        $shippingAddress->expects($this->once())->method('addData')
            ->with(['firstname' => 'Jane'])->willReturnSelf();
        $shippingAddress->expects($this->once())->method('setCollectShippingRates')
            ->with(true)->willReturnSelf();
        $billingAddress->expects($this->once())->method('addData')
            ->with(['firstname' => 'John'])->willReturnSelf();
        $config->method('getEnvironmentType')->willReturn('sandbox');
        $orderHelper->method('validateCheckoutLocation')->with('CART')->willReturn('CART');

        $metadata = [];
        $payment->expects($this->exactly(5))->method('setAdditionalInformation')
            ->willReturnCallback(static function (string $key, string $value) use (&$metadata, $payment): Payment {
                $metadata[$key] = $value;
                return $payment;
            });
        $quoteStateManager->expects($this->once())->method('discard')->with($quote);
        $quote->expects($this->once())->method('collectTotals')->willReturnSelf();
        $quoteRepository->expects($this->once())->method('save')->with($quote);

        $checkout = new SmartButtonsCheckout(
            $quoteRepository,
            $this->createMock(CartManagementInterface::class),
            $this->createMock(Data::class),
            $this->createMock(CustomerSession::class),
            $checkoutSession,
            $paypalSession,
            $this->createMock(OrderSender::class),
            $this->createMock(OrderService::class),
            $this->createMock(LoggerInterface::class),
            $config,
            $this->createMock(Data::class),
            $orderHelper,
            $quoteStateManager
        );

        $checkout->updateQuote(
            ['firstname' => 'Jane'],
            ['firstname' => 'John'],
            'PAYPAL-ID',
            'PAYER-ID',
            'PAYMENTS-ID',
            'CART'
        );

        $this->assertSame([
            'paypal_payer_id' => 'PAYER-ID',
            'paypal_order_id' => 'PAYPAL-ID',
            'payments_order_id' => 'PAYMENTS-ID',
            'payments_mode' => 'sandbox',
            'location' => 'CART'
        ], $metadata);
    }
}

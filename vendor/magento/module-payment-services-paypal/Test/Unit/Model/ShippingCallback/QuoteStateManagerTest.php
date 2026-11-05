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
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TypeError;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuoteStateManagerTest extends TestCase
{
    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $quoteRepository;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /** @var QuoteStateManager */
    private QuoteStateManager $quoteStateManager;

    protected function setUp(): void
    {
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->quoteStateManager = new QuoteStateManager($this->quoteRepository, $this->logger);
    }

    public function testCaptureStoresOnlyPayPalOrderAndCallbackMutableValues(): void
    {
        $payment = $this->createMock(Payment::class);
        $shippingAddress = $this->createAddress([
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'telephone' => '1234567890',
            'city' => 'Austin',
            'country_id' => 'US',
            'region' => 'Texas',
            'region_id' => 57,
            'region_code' => 'TX',
            'postcode' => '78701',
            'should_ignore_validation' => false,
            'street' => ['100 Main Street'],
            'email' => 'private@example.com',
            'company' => 'Example'
        ]);
        $billingAddress = $this->createAddress(['firstname' => 'John']);
        $shippingAddress->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $shippingAddress->method('getShippingDescription')->willReturn('Flat Rate');
        $shippingAddress->method('getLimitCarrier')->willReturn('flatrate');
        $quote = $this->createQuote($payment, $shippingAddress, $billingAddress);

        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => $field === 'paypal_order_id' ? 'PAYPAL-ORDER-ID' : null
        );
        $payment->expects($this->once())->method('setAdditionalInformation')->with(
            QuoteStateManager::SNAPSHOT_KEY,
            $this->callback(static function (array $snapshot): bool {
                $expectedFields = [
                    'firstname',
                    'lastname',
                    'telephone',
                    'city',
                    'country_id',
                    'region',
                    'region_id',
                    'region_code',
                    'postcode',
                    'should_ignore_validation'
                ];

                return $snapshot['paypal_order_id'] === 'PAYPAL-ORDER-ID'
                    && array_keys($snapshot['shipping_address']) === $expectedFields
                    && array_keys($snapshot['billing_address']) === $expectedFields
                    && !array_key_exists('session_id', $snapshot)
                    && !array_key_exists('street', $snapshot['shipping_address'])
                    && !array_key_exists('email', $snapshot['shipping_address'])
                    && !array_key_exists('company', $snapshot['shipping_address']);
            })
        );

        $this->quoteStateManager->capture($quote);
    }

    public function testCaptureKeepsFirstSnapshotForSamePayPalOrder(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => match ($field) {
                'paypal_order_id' => 'PAYPAL-ORDER-ID',
                QuoteStateManager::SNAPSHOT_KEY => [
                    'paypal_order_id' => 'PAYPAL-ORDER-ID',
                    'shipping_address' => ['firstname' => 'Original']
                ],
                default => null
            }
        );
        $payment->expects($this->never())->method('setAdditionalInformation');
        $address = $this->createAddressMock();

        $this->quoteStateManager->capture($this->createQuote($payment, $address, $address));
    }

    public function testCaptureReplacesSnapshotOwnedByOlderPayPalOrder(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => match ($field) {
                'paypal_order_id' => 'NEW-ORDER',
                QuoteStateManager::SNAPSHOT_KEY => ['paypal_order_id' => 'OLD-ORDER'],
                default => null
            }
        );
        $address = $this->createAddress([]);
        $quote = $this->createQuote($payment, $address, $address);

        $payment->expects($this->once())->method('setAdditionalInformation')
            ->with(QuoteStateManager::SNAPSHOT_KEY, $this->callback(static function (array $snapshot): bool {
                return $snapshot['paypal_order_id'] === 'NEW-ORDER'
                    && !array_key_exists('session_id', $snapshot);
            }));

        $this->quoteStateManager->capture($quote);
    }

    public function testCaptureRejectsMissingPayPalOrderId(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(null);
        $payment->expects($this->never())->method('setAdditionalInformation');
        $address = $this->createAddressMock();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to process shipping callback');

        $this->quoteStateManager->capture($this->createQuote($payment, $address, $address));
    }

    public function testRestoreDiscardsMismatchedSnapshotWithoutClearingNewerAttempt(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => match ($field) {
                QuoteStateManager::SNAPSHOT_KEY => ['paypal_order_id' => 'OLD-ORDER'],
                'paypal_order_id' => 'NEW-ORDER',
                QuoteStateManager::SESSION_ID_KEY => 'NEW-SESSION',
                default => null
            }
        );
        $payment->expects($this->once())->method('unsAdditionalInformation')
            ->with(QuoteStateManager::SNAPSHOT_KEY);
        $address = $this->createAddressMock();
        $quote = $this->createQuote($payment, $address, $address);
        $address->expects($this->never())->method('addData');
        $quote->expects($this->never())->method('collectTotals');
        $this->quoteRepository->expects($this->once())->method('save')->with($quote);

        $this->assertFalse($this->quoteStateManager->restore($quote));
    }

    public function testRestoreWithoutSnapshotClearsAbandonedOrderAndSessionData(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => $field === QuoteStateManager::SESSION_ID_KEY ? 'SESSION-ID' : null
        );
        $clearedFields = [];
        $payment->expects($this->exactly(6))->method('unsAdditionalInformation')
            ->willReturnCallback(static function (string $field) use (&$clearedFields, $payment): Payment {
                $clearedFields[] = $field;
                return $payment;
            });
        $address = $this->createAddressMock();
        $quote = $this->createQuote($payment, $address, $address);
        $quote->expects($this->never())->method('collectTotals');
        $this->quoteRepository->expects($this->once())->method('save')->with($quote);

        $this->assertFalse($this->quoteStateManager->restore($quote));
        $this->assertSame([
            QuoteStateManager::SNAPSHOT_KEY,
            'paypal_order_id',
            'payments_order_id',
            'paypal_order_amount',
            'paypal_payer_id',
            QuoteStateManager::SESSION_ID_KEY
        ], $clearedFields);
    }

    public function testActiveAttemptDependsOnlyOnRecoveryMetadata(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => match ($field) {
                'payment_source' => 'googlepay',
                QuoteStateManager::SNAPSHOT_KEY => ['paypal_order_id' => 'PAYPAL-ORDER-ID'],
                default => null
            }
        );
        $address = $this->createAddressMock();

        $this->assertTrue(
            $this->quoteStateManager->hasActiveSsscAttempt($this->createQuote($payment, $address, $address))
        );
    }

    public function testRestoreRestoresAddressShippingAssignmentRatesAndTotals(): void
    {
        $snapshot = [
            'paypal_order_id' => 'PAYPAL-ORDER-ID',
            'shipping_address' => ['firstname' => 'Jane', 'region_code' => 'TX'],
            'billing_address' => ['firstname' => 'John'],
            'shipping_method' => 'flatrate_flatrate',
            'shipping_description' => 'Flat Rate',
            'shipping_limit_carrier' => 'flatrate'
        ];
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => match ($field) {
                QuoteStateManager::SNAPSHOT_KEY => $snapshot,
                'paypal_order_id' => 'PAYPAL-ORDER-ID',
                QuoteStateManager::SESSION_ID_KEY => 'SESSION-ID',
                default => null
            }
        );
        $shippingAddress = $this->createAddressMock();
        $billingAddress = $this->createAddressMock();
        $shipping = $this->createMock(ShippingInterface::class);
        $assignment = $this->createMock(ShippingAssignmentInterface::class);
        $extensionAttributes = new CartExtensionWithShippingAssignments();
        $assignment->method('getShipping')->willReturn($shipping);
        $extensionAttributes->setShippingAssignments([$assignment]);
        $quote = $this->createQuote($payment, $shippingAddress, $billingAddress, $extensionAttributes);

        $shippingAddress->expects($this->once())->method('addData')->with($snapshot['shipping_address']);
        $shippingAddress->expects($this->once())->method('setShippingMethod')->with('flatrate_flatrate');
        $shippingAddress->expects($this->once())->method('setShippingDescription')->with('Flat Rate');
        $shippingAddress->expects($this->once())->method('setLimitCarrier')->with('flatrate');
        $shippingAddress->expects($this->once())->method('removeAllShippingRates');
        $shippingAddress->expects($this->once())->method('setCollectShippingRates')->with(true);
        $billingAddress->expects($this->once())->method('addData')->with($snapshot['billing_address']);
        $shipping->expects($this->once())->method('setAddress')->with($shippingAddress);
        $shipping->expects($this->once())->method('setMethod')->with('flatrate_flatrate');
        $payment->expects($this->exactly(6))->method('unsAdditionalInformation');
        $quote->expects($this->once())->method('setTotalsCollectedFlag')->with(false);
        $quote->expects($this->once())->method('collectTotals');
        $this->quoteRepository->expects($this->once())->method('save')->with($quote);

        $this->assertTrue($this->quoteStateManager->restore($quote));
    }

    public function testRecoverLogsOnlyExceptionClassAndCode(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => match ($field) {
                'payment_source' => 'paypal',
                QuoteStateManager::SESSION_ID_KEY => 'SECRET-SESSION-ID',
                default => null
            }
        );
        $address = $this->createAddressMock();
        $quote = $this->createQuote($payment, $address, $address);
        $exception = new RuntimeException('Sensitive address and payment data', 73);
        $this->quoteRepository->expects($this->once())->method('save')->willThrowException($exception);
        $this->logger->expects($this->once())->method('error')->with(
            'Unable to recover quote after a failed PayPal operation.',
            ['exception_type' => RuntimeException::class, 'exception_code' => 73]
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to restore the cart. Please try again.');

        $this->quoteStateManager->recover($quote);
    }

    public function testRecoverWrapsNonExceptionThrowableInLocalizedException(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (string $field) => $field === QuoteStateManager::SESSION_ID_KEY ? 'SESSION-ID' : null
        );
        $address = $this->createAddressMock();
        $quote = $this->createQuote($payment, $address, $address);
        $throwable = new TypeError('Invalid quote state.');
        $this->quoteRepository->expects($this->once())->method('save')->willThrowException($throwable);
        $this->logger->expects($this->once())->method('error')->with(
            'Unable to recover quote after a failed PayPal operation.',
            ['exception_type' => TypeError::class, 'exception_code' => 0]
        );

        try {
            $this->quoteStateManager->recover($quote);
            $this->fail('Expected quote recovery to fail.');
        } catch (LocalizedException $exception) {
            $this->assertSame('Unable to restore the cart. Please try again.', $exception->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            $this->assertSame($throwable, $exception->getPrevious()?->getPrevious());
        }
    }

    public function testDiscardRemovesSnapshotAndSessionOnly(): void
    {
        $payment = $this->createMock(Payment::class);
        $clearedFields = [];
        $payment->expects($this->exactly(2))->method('unsAdditionalInformation')
            ->willReturnCallback(static function (string $field) use (&$clearedFields, $payment): Payment {
                $clearedFields[] = $field;
                return $payment;
            });
        $address = $this->createAddressMock();

        $this->quoteStateManager->discard($this->createQuote($payment, $address, $address));

        $this->assertSame([QuoteStateManager::SNAPSHOT_KEY, QuoteStateManager::SESSION_ID_KEY], $clearedFields);
    }

    private function createAddress(array $data): Address&MockObject
    {
        $address = $this->createAddressMock();
        $address->method('getData')->willReturnCallback(
            static fn (string $field) => $data[$field] ?? null
        );

        return $address;
    }

    private function createAddressMock(): Address&MockObject
    {
        return $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'addData', 'removeAllShippingRates', 'getShippingMethod'])
            ->addMethods([
                'getShippingDescription',
                'getLimitCarrier',
                'setShippingMethod',
                'setShippingDescription',
                'setLimitCarrier',
                'setCollectShippingRates'
            ])
            ->getMock();
    }

    private function createQuote(
        Payment $payment,
        Address $shippingAddress,
        Address $billingAddress,
        ?CartExtensionInterface $extensionAttributes = null
    ): Quote&MockObject {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getPayment',
                'getShippingAddress',
                'getBillingAddress',
                'getExtensionAttributes',
                'collectTotals'
            ])
            ->addMethods(['setTotalsCollectedFlag'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getExtensionAttributes')->willReturn($extensionAttributes);

        return $quote;
    }
}

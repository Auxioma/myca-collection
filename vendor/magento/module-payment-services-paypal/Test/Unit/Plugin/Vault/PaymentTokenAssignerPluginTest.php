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

namespace Magento\PaymentServicesPaypal\Test\Unit\Plugin\Vault;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\PaymentServicesPaypal\Plugin\Vault\PaymentTokenAssignerPlugin;
use Magento\Quote\Model\Quote\Payment;
use Magento\Vault\Observer\PaymentTokenAssigner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for PaymentTokenAssignerPlugin
 */
class PaymentTokenAssignerPluginTest extends TestCase
{
    private const VAULT_METHOD_CODE = 'payment_services_paypal_vault';
    private const OTHER_METHOD_CODE = 'payment_services_paypal_smart_buttons';
    private const PAYPAL_ORDER_ID = 'PAYPAL-ORDER-123';
    private const PAYPAL_ORDER_AMOUNT = '100.00';

    /**
     * @var PaymentTokenAssignerPlugin
     */
    private PaymentTokenAssignerPlugin $plugin;

    /**
     * @var PaymentTokenAssigner|MockObject
     */
    private PaymentTokenAssigner|MockObject $subject;

    /**
     * @var Observer|MockObject
     */
    private Observer|MockObject $observer;

    /**
     * @var Event|MockObject
     */
    private Event|MockObject $event;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $payment;

    /**
     * Spy array capturing every setAdditionalInformation($field, $value) call made on the payment mock.
     *
     * @var array<string, mixed>
     */
    private array $capturedRestorations = [];

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        $this->subject = $this->createMock(PaymentTokenAssigner::class);
        $this->observer = $this->createMock(Observer::class);
        $this->event = $this->createMock(Event::class);
        $this->payment = $this->createMock(Payment::class);

        $this->observer->method('getEvent')->willReturn($this->event);

        $this->plugin = new PaymentTokenAssignerPlugin();
    }

    /**
     * Non-vault method: plugin must delegate straight to $proceed without touching additional data.
     */
    public function testAroundExecuteSkipsPreservationForNonVaultPaymentMethod(): void
    {
        // Having: a payment whose method code is not the vault code
        $this->havingPaymentWithMethod(self::OTHER_METHOD_CODE);
        $this->havingSetAdditionalInformationIsNeverExpected();
        $proceed = $this->havingProceedExpectedOnce();

        // When: the plugin executes
        $this->whenAroundExecuteIsCalled($proceed);

        // Then: expectations set in the having section are the assertions
        $this->thenExpectationsAreVerified();
    }

    /**
     * Null payment: event returns null instead of an InfoInterface object; plugin must not crash.
     */
    public function testAroundExecuteSkipsPreservationWhenPaymentIsNull(): void
    {
        // Having: the event provides null where a payment object is expected
        $this->event->method('getDataByKey')
            ->with(AbstractDataAssignObserver::MODEL_CODE)
            ->willReturn(null);
        $proceed = $this->havingProceedExpectedOnce();

        // When: the plugin executes
        $this->whenAroundExecuteIsCalled($proceed);

        // Then: expectations set in the having section are the assertions
        $this->thenExpectationsAreVerified();
    }

    /**
     * Both fields populated: plugin must restore paypal_order_id and paypal_order_amount after the wipe.
     */
    public function testAroundExecutePreservesBothFieldsWhenBothAreSet(): void
    {
        // Having: a vault payment with both preserved fields populated before the wipe
        $this->havingPaymentWithMethod(self::VAULT_METHOD_CODE);
        $this->havingPaymentHasFields([
            'paypal_order_id'     => self::PAYPAL_ORDER_ID,
            'paypal_order_amount' => self::PAYPAL_ORDER_AMOUNT,
        ]);
        $proceed = $this->havingProceedExpectedOnce();

        // When: the plugin executes
        $this->whenAroundExecuteIsCalled($proceed);

        // Then: both fields are written back after the wipe
        $this->thenFieldWasRestoredWith('paypal_order_id', self::PAYPAL_ORDER_ID);
        $this->thenFieldWasRestoredWith('paypal_order_amount', self::PAYPAL_ORDER_AMOUNT);
    }

    /**
     * Only one field populated: plugin must restore only the field that had a non-null value.
     */
    public function testAroundExecutePreservesOnlyNonNullFields(): void
    {
        // Having: a vault payment where paypal_order_amount is absent (null)
        $this->havingPaymentWithMethod(self::VAULT_METHOD_CODE);
        $this->havingPaymentHasFields([
            'paypal_order_id'     => self::PAYPAL_ORDER_ID,
            'paypal_order_amount' => null,
        ]);
        $proceed = $this->havingProceedExpectedOnce();

        // When: the plugin executes
        $this->whenAroundExecuteIsCalled($proceed);

        // Then: only the non-null field is written back
        $this->thenFieldWasRestoredWith('paypal_order_id', self::PAYPAL_ORDER_ID);
        $this->thenFieldWasNotRestored('paypal_order_amount');
    }

    /**
     * All fields null: plugin must call $proceed but must not write any field back.
     */
    public function testAroundExecuteSkipsRestorationWhenAllFieldsAreNull(): void
    {
        // Having: a vault payment where both preserved fields return null
        $this->havingPaymentWithMethod(self::VAULT_METHOD_CODE);
        $this->havingPaymentHasFields([
            'paypal_order_id'     => null,
            'paypal_order_amount' => null,
        ]);
        $proceed = $this->havingProceedExpectedOnce();

        // When: the plugin executes
        $this->whenAroundExecuteIsCalled($proceed);

        // Then: nothing is written back
        $this->thenNoFieldsWereRestored();
    }

    // -------------------------------------------------------------------------
    // Having (setup) helpers
    // -------------------------------------------------------------------------

    /**
     * Setup: the observer's event returns the payment mock with the given method code.
     *
     * @param string $methodCode
     * @return void
     */
    private function havingPaymentWithMethod(string $methodCode): void
    {
        $this->event->method('getDataByKey')
            ->with(AbstractDataAssignObserver::MODEL_CODE)
            ->willReturn($this->payment);

        $this->payment->method('getMethod')->willReturn($methodCode);
    }

    /**
     * Setup: the payment mock returns the given additional-information map and records
     * any setAdditionalInformation calls in $capturedRestorations.
     *
     * @param array<string, mixed> $fields
     * @return void
     */
    private function havingPaymentHasFields(array $fields): void
    {
        $this->payment->method('getAdditionalInformation')
            ->willReturnCallback(function (string $key) use ($fields): mixed {
                return $fields[$key] ?? null;
            });

        $this->capturedRestorations = [];

        $this->payment->method('setAdditionalInformation')
            ->willReturnCallback(function (string $key, mixed $value): void {
                $this->capturedRestorations[$key] = $value;
            });
    }

    /**
     * Setup: the payment mock must never have setAdditionalInformation called on it.
     *
     * @return void
     */
    private function havingSetAdditionalInformationIsNeverExpected(): void
    {
        $this->payment->expects($this->never())->method('setAdditionalInformation');
    }

    /**
     * Setup: creates a callable spy that asserts it is invoked exactly once with the observer.
     *
     * @return callable
     */
    private function havingProceedExpectedOnce(): callable
    {
        $proceed = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

        $proceed->expects($this->once())
            ->method('__invoke')
            ->with($this->observer);

        return $proceed;
    }

    // -------------------------------------------------------------------------
    // When (action) helpers
    // -------------------------------------------------------------------------

    /**
     * Action: invoke aroundExecute on the plugin under test.
     *
     * @param callable $proceed
     * @return void
     */
    private function whenAroundExecuteIsCalled(callable $proceed): void
    {
        $this->plugin->aroundExecute($this->subject, $proceed, $this->observer);
    }

    // -------------------------------------------------------------------------
    // Then (assertion) helpers
    // -------------------------------------------------------------------------

    /**
     * Assert: the given field was passed to setAdditionalInformation with the expected value.
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function thenFieldWasRestoredWith(string $field, mixed $value): void
    {
        $this->assertArrayHasKey($field, $this->capturedRestorations);
        $this->assertSame($value, $this->capturedRestorations[$field]);
    }

    /**
     * Assert: the given field was NOT passed to setAdditionalInformation.
     *
     * @param string $field
     * @return void
     */
    private function thenFieldWasNotRestored(string $field): void
    {
        $this->assertArrayNotHasKey($field, $this->capturedRestorations);
    }

    /**
     * Assert: setAdditionalInformation was not called at all.
     *
     * @return void
     */
    private function thenNoFieldsWereRestored(): void
    {
        $this->assertEmpty($this->capturedRestorations);
    }

    /**
     * Assert: all mock expectations set in the having section are verified by PHPUnit at teardown.
     *
     * @return void
     */
    private function thenExpectationsAreVerified(): void
    {
        $this->addToAssertionCount(1);
    }
}

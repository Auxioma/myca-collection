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

namespace Magento\PaymentServicesPaypal\Test\Unit\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\PaymentServicesPaypal\Gateway\Response\PaymentInformationHandler;
use Magento\PaymentServicesPaypal\Gateway\Response\TxnIdHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for PaymentInformationHandler
 */
class PaymentInformationHandlerTest extends TestCase
{
    private const EXPECTED_PAYPAL_DEBUG_ID = 'debug-abc-123';
    private const EXPECTED_PAYER_EMAIL = 'buyer@example.com';

    /**
     * @var PaymentInformationHandler
     */
    private PaymentInformationHandler $handler;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private PaymentDataObjectInterface|MockObject $paymentDO;

    /**
     * @var InfoInterface|MockObject
     */
    private InfoInterface|MockObject $payment;

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        $this->paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $this->payment = $this->createMock(InfoInterface::class);
        $this->handler = new PaymentInformationHandler();
    }

    /**
     * Test that an exception is thrown when no payment key is present in handling subject
     */
    public function testHandleThrowsExceptionWhenPaymentNotInHandlingSubject(): void
    {
        // Having: a handling subject without the 'payment' key
        $handlingSubject = [];
        $response = [];

        // When/Then: handle is called, an exception is thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that an exception is thrown when the payment value is not a PaymentDataObjectInterface
     */
    public function testHandleThrowsExceptionWhenPaymentIsNotPaymentDataObject(): void
    {
        // Having: a handling subject with an invalid 'payment' value
        $handlingSubject = ['payment' => new \stdClass()];
        $response = [];

        // When/Then: handle is called, an exception is thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that no additional information is stored when the response has no mp-transaction key
     * (defensive check for an unexpected response shape)
     */
    public function testHandleSkipsStoringWhenMpTransactionMissing(): void
    {
        // Having: a valid payment but a response with no mp-transaction key at all
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [];

        // Then: no additional information is stored on the payment
        $this->thenNoAdditionalInformationIsStored();

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that no additional information is stored when both payment-info fields are absent
     * (backwards compatibility with backend versions that don't yet emit them)
     */
    public function testHandleSkipsStoringWhenBothFieldsMissing(): void
    {
        // Having: a valid payment and a mp-transaction without the new fields
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::AUTH_TXN,
            ],
        ];

        // Then: no additional information is stored on the payment
        $this->thenNoAdditionalInformationIsStored();

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that no additional information is stored when both fields are present but empty
     * (PayPal can return empty strings; the handler treats them like missing)
     */
    public function testHandleSkipsStoringWhenBothFieldsEmpty(): void
    {
        // Having: a valid payment and a mp-transaction with empty/null values
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::AUTH_TXN,
                PaymentInformationHandler::PAYPAL_DEBUG_ID_KEY => '',
                PaymentInformationHandler::PAYER_EMAIL_KEY => null,
            ],
        ];

        // Then: no additional information is stored
        $this->thenNoAdditionalInformationIsStored();

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that only paypal_debug_id is stored when payer_email is missing
     */
    public function testHandleStoresOnlyPaypalDebugIdWhenPayerEmailMissing(): void
    {
        // Having: a valid payment and a mp-transaction with only paypal_debug_id
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::AUTH_TXN,
                PaymentInformationHandler::PAYPAL_DEBUG_ID_KEY => self::EXPECTED_PAYPAL_DEBUG_ID,
            ],
        ];

        // Then: only paypal_debug_id is stored, exactly once
        $this->payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(
                PaymentInformationHandler::PAYPAL_DEBUG_ID_KEY,
                self::EXPECTED_PAYPAL_DEBUG_ID
            );

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that only payer_email is stored when paypal_debug_id is missing
     */
    public function testHandleStoresOnlyPayerEmailWhenPaypalDebugIdMissing(): void
    {
        // Having: a valid payment and an mp-transaction with only payer_email
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::CAPTURE_TXN,
                PaymentInformationHandler::PAYER_EMAIL_KEY => self::EXPECTED_PAYER_EMAIL,
            ],
        ];

        // Then: only payer_email is stored, exactly once
        $this->payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(
                PaymentInformationHandler::PAYER_EMAIL_KEY,
                self::EXPECTED_PAYER_EMAIL
            );

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that both fields are stored when present in the response
     */
    public function testHandleStoresBothFieldsWhenPresent(): void
    {
        // Having: a valid payment and an mp-transaction with both new fields populated
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::AUTH_TXN,
                PaymentInformationHandler::PAYPAL_DEBUG_ID_KEY => self::EXPECTED_PAYPAL_DEBUG_ID,
                PaymentInformationHandler::PAYER_EMAIL_KEY => self::EXPECTED_PAYER_EMAIL,
            ],
        ];

        // Then: both keys are stored as additional information
        $this->thenBothPaymentInformationKeysAreStored(
            self::EXPECTED_PAYPAL_DEBUG_ID,
            self::EXPECTED_PAYER_EMAIL
        );

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Action: invoke handle on the SUT
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    private function whenHandlingResponse(array $handlingSubject, array $response): void
    {
        $this->handler->handle($handlingSubject, $response);
    }

    /**
     * Assert: setAdditionalInformation must never be called
     *
     * @return void
     */
    private function thenNoAdditionalInformationIsStored(): void
    {
        $this->payment->expects($this->never())->method('setAdditionalInformation');
    }

    /**
     * Assert: setAdditionalInformation is called once for paypal_debug_id and once for payer_email
     *
     * @param string $expectedPaypalDebugId
     * @param string $expectedPayerEmail
     * @return void
     */
    private function thenBothPaymentInformationKeysAreStored(
        string $expectedPaypalDebugId,
        string $expectedPayerEmail
    ): void {
        $callCount = 0;
        $this->payment->expects($this->exactly(2))
            ->method('setAdditionalInformation')
            ->willReturnCallback(
                function (
                    string $key,
                    mixed $value
                ) use (
                    &$callCount,
                    $expectedPaypalDebugId,
                    $expectedPayerEmail
                ): void {
                    $callCount++;
                    switch ($callCount) {
                        case 1:
                            $this->assertEquals(PaymentInformationHandler::PAYPAL_DEBUG_ID_KEY, $key);
                            $this->assertEquals($expectedPaypalDebugId, $value);
                            break;
                        case 2:
                            $this->assertEquals(PaymentInformationHandler::PAYER_EMAIL_KEY, $key);
                            $this->assertEquals($expectedPayerEmail, $value);
                            break;
                    }
                }
            );
    }
}

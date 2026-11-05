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
use Magento\PaymentServicesPaypal\Gateway\Response\TransactionAmountHandler;
use Magento\PaymentServicesPaypal\Gateway\Response\TxnIdHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for TransactionAmountHandler
 */
class TransactionAmountHandlerTest extends TestCase
{
    /**
     * @var TransactionAmountHandler
     */
    private TransactionAmountHandler $handler;

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
        $this->handler = new TransactionAmountHandler();
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
     * Test that amount data is not stored when transaction type is not authorization or auth_capture
     */
    public function testHandleSkipsStoringAmountForNonAuthTxnType(): void
    {
        // Having: a valid payment and a txn type that is neither auth nor auth_capture
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => 'capture',
                'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
            ],
        ];

        // Then: no additional information is stored on the payment
        $this->thenNoAdditionalInformationIsStored();

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that amount data is not stored when amount is missing from the response
     */
    public function testHandleSkipsStoringAmountWhenAmountMissingFromResponse(): void
    {
        // Having: a valid payment and an auth txn type but no amount in the response
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
     * Test that amount and currency are stored for an authorization transaction
     */
    public function testHandleStoresAmountDataForAuthorizationTxn(): void
    {
        // Having: a valid payment and an authorization txn with an amount
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::AUTH_TXN,
                'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
            ],
        ];

        // When: handle is called
        $this->thenAmountAndCurrencyAreStoredInAdditionalInformation('100.00', 'USD');
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that amount and currency are stored for an auth_capture transaction
     */
    public function testHandleStoresAmountDataForAuthCaptureTxn(): void
    {
        // Having: a valid payment and an auth_capture txn with an amount
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::AUTH_CAPTURE_TXN,
                'amount' => ['value' => '250.50', 'currency_code' => 'EUR'],
            ],
        ];

        // When: handle is called
        $this->thenAmountAndCurrencyAreStoredInAdditionalInformation('250.50', 'EUR');
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
     * Assert: setAdditionalInformation must be called with amount key then currency key
     *
     * @param string $expectedAmount
     * @param string $expectedCurrency
     * @return void
     */
    private function thenAmountAndCurrencyAreStoredInAdditionalInformation(
        string $expectedAmount,
        string $expectedCurrency
    ): void {
        $callCount = 0;
        $this->payment->expects($this->exactly(2))
            ->method('setAdditionalInformation')
            ->willReturnCallback(
                function (
                    string $key,
                    ?string $value
                ) use (
                    &$callCount,
                    $expectedAmount,
                    $expectedCurrency
                ): void {
                    $callCount++;
                    switch ($callCount) {
                        case 1:
                            $this->assertEquals(TransactionAmountHandler::PP_ORDER_AMOUNT_KEY, $key);
                            $this->assertEquals($expectedAmount, $value);
                            break;
                        case 2:
                            $this->assertEquals(TransactionAmountHandler::PP_ORDER_CURRENCY_KEY, $key);
                            $this->assertEquals($expectedCurrency, $value);
                            break;
                    }
                }
            );
    }
}

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
use Magento\PaymentServicesPaypal\Gateway\Response\SellerProtectionHandler;
use Magento\PaymentServicesPaypal\Gateway\Response\TxnIdHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for SellerProtectionHandler
 */
class SellerProtectionHandlerTest extends TestCase
{
    /**
     * @var SellerProtectionHandler
     */
    private SellerProtectionHandler $handler;

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
        $this->handler = new SellerProtectionHandler();
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
     * Test that no additional information is stored when seller_protection is missing from response
     */
    public function testHandleSkipsStoringWhenSellerProtectionMissing(): void
    {
        // Having: a valid payment but no seller_protection in the response
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
     * Test that status is stored on the payment when a full seller_protection payload is present
     * (PayPal returns dispute_categories alongside an ELIGIBLE / PARTIALLY_ELIGIBLE status but
     * only persists the status)
     */
    public function testHandleStoresStatusWhenEligible(): void
    {
        $expectedStatus = 'ELIGIBLE';

        // Having: a valid payment and a populated seller_protection block on an authorization
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::AUTH_TXN,
                'seller_protection' => [
                    'status' => $expectedStatus,
                    'dispute_categories' => ['ITEM_NOT_RECEIVED', 'UNAUTHORIZED_TRANSACTION'],
                ],
            ],
        ];

        // Then: only the status key is stored
        $this->payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(SellerProtectionHandler::SELLER_PROTECTION_STATUS_KEY, $expectedStatus);

        // When: handle is called
        $this->whenHandlingResponse($handlingSubject, $response);
    }

    /**
     * Test that only status is stored when dispute_categories is omitted by PayPal
     * (PayPal omits dispute_categories when status is NOT_ELIGIBLE)
     */
    public function testHandleStoresOnlyStatusWhenNotEligible(): void
    {
        $eligibilityStatus = 'NOT_ELIGIBLE';

        // Having: a valid payment and a seller_protection block with status only on a capture
        $this->paymentDO->method('getPayment')->willReturn($this->payment);
        $handlingSubject = ['payment' => $this->paymentDO];
        $response = [
            'mp-transaction' => [
                'type' => TxnIdHandler::CAPTURE_TXN,
                'seller_protection' => ['status' => $eligibilityStatus],
            ],
        ];

        // Then: only the status key is stored
        $this->payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(SellerProtectionHandler::SELLER_PROTECTION_STATUS_KEY, $eligibilityStatus);

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
}

<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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
namespace PayPal\Braintree\Test\Unit\Gateway\Response;

use Braintree\Transaction;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Response\ThreeDSecureDetailsHandler;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

class ThreeDSecureDetailsHandlerTest extends \PHPUnit\Framework\TestCase
{
    public const TRANSACTION_ID = '432er5ww3e';

    /**
     * @var ThreeDSecureDetailsHandler
     */
    private ThreeDSecureDetailsHandler $handler;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $payment;

    /**
     * @var SubjectReader|MockObject
     */
    private MockObject|SubjectReader $subjectReaderMock;

    protected function setUp(): void
    {
        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'unsAdditionalInformation',
                'hasAdditionalInformation',
                'setAdditionalInformation',
            ])
            ->getMock();

        $this->subjectReaderMock = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->handler = new ThreeDSecureDetailsHandler($this->subjectReaderMock);
    }

    public function testHandle()
    {
        $this->markTestSkipped('Skip this test');
        $paymentData = $this->getPaymentDataObjectMock();
        $transaction = $this->getBraintreeTransaction();

        $subject = ['payment' => $paymentData];
        $response = ['object' => $transaction];

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($paymentData);
        $this->subjectReaderMock->expects(self::once())
            ->method('readTransaction')
            ->with($response)
            ->willReturn($transaction);

        $this->payment->expects(static::once(1))
            ->method('setAdditionalInformation')
            ->with('liabilityShifted', 'Yes');
        $this->payment->expects(static::once(2))
            ->method('setAdditionalInformation')
            ->with('liabilityShiftPossible', 'Yes');

        $this->handler->handle($subject, $response);
    }

    /**
     * Create mock for payment data object and order payment
     * @return MockObject
     */
    private function getPaymentDataObjectMock(): MockObject
    {
        $mock = $this->getMockBuilder(PaymentDataObject::class)
            ->onlyMethods(['getPayment'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->payment);

        return $mock;
    }

    /**
     * Create Braintree transaction
     * @return Transaction
     */
    private function getBraintreeTransaction(): Transaction
    {
        $attributes = [
            'id' => self::TRANSACTION_ID,
            'threeDSecureInfo' => $this->getThreeDSecureInfo()
        ];

        return Transaction::factory($attributes);
    }

    /**
     * Get 3d secure details
     *
     * @return array
     */
    private function getThreeDSecureInfo(): array
    {
        return [
            'liabilityShifted' => 'Yes',
            'liabilityShiftPossible' => 'Yes'
        ];
    }
}

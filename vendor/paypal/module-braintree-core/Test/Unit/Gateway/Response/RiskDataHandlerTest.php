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
use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Response\RiskDataHandler;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

/**
 * @see \PayPal\Braintree\Gateway\Response\RiskDataHandler
 */
class RiskDataHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RiskDataHandler
     */
    private RiskDataHandler $riskDataHandler;

    /**
     * @var SubjectReader|MockObject
     */
    private SubjectReader|MockObject $subjectReader;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->subjectReader = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['readPayment', 'readTransaction'])
            ->getMock();

        $this->riskDataHandler = new RiskDataHandler($this->subjectReader);
    }

    /**
     * Test for handle method
     *
     * @covers \PayPal\Braintree\Gateway\Response\RiskDataHandler::handle
     * @param string $riskDecision
     * @param bool $isFraud
     * @throws LocalizedException
     * @throws Exception
     */
    #[DataProvider('riskDataProvider')]
    public function testHandle(string $riskDecision, bool $isFraud)
    {
        $this->markTestSkipped('Skip this test');
        /** @var Payment|MockObject $payment */
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setAdditionalInformation', 'setIsFraudDetected'])
            ->getMock();
        /** @var PaymentDataObjectInterface|MockObject $paymentDO */
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->expects(self::once())
            ->method('getPayment')
            ->willReturn($payment);

        $transaction = Transaction::factory([
            'riskData' => [
                'id' => 'test-id',
                'decision' => $riskDecision
            ]
        ]);

        $response = [
            'object' => $transaction
        ];
        $handlingSubject = [
            'payment' => $paymentDO,
        ];

        $this->subjectReader->expects(static::once())
            ->method('readPayment')
            ->with($handlingSubject)
            ->willReturn($paymentDO);
        $this->subjectReader->expects(static::once())
            ->method('readTransaction')
            ->with($response)
            ->willReturn($transaction);

        $payment->expects(static::once(0))
            ->method('setAdditionalInformation')
            ->with(RiskDataHandler::RISK_DATA_ID, 'test-id');
        $payment->expects(static::once(1))
            ->method('setAdditionalInformation')
            ->with(RiskDataHandler::RISK_DATA_DECISION, $riskDecision);

        if (!$isFraud) {
            $payment->expects(static::never())
                ->method('setIsFraudDetected');
        } else {
            $payment->expects(static::once())
                ->method('setIsFraudDetected')
                ->with(true);
        }

        $this->riskDataHandler->handle($handlingSubject, $response);
    }

    /**
     * Get list of variations to test fraud
     *
     * @return array
     */
    public static function riskDataProvider(): array
    {
        return [
            ['Not Evaluated', false],
            ['Approve', false],
            ['Review', true],
        ];
    }
}

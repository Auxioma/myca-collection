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
namespace PayPal\Braintree\Test\Unit\Gateway\Request\PayPal;

use PayPal\Braintree\Gateway\Helper\SubjectReader;
use PayPal\Braintree\Gateway\Request\PayPal\DeviceDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

class DeviceDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SubjectReader|MockObject
     */
    private SubjectReader|MockObject $subjectReader;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private PaymentDataObjectInterface|MockObject $paymentDataObject;

    /**
     * @var InfoInterface|MockObject
     */
    private InfoInterface|MockObject $paymentInfo;

    /**
     * @var DeviceDataBuilder
     */
    private DeviceDataBuilder $builder;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->subjectReader = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['readPayment'])
            ->getMock();

        $this->paymentDataObject = $this->createMock(PaymentDataObjectInterface::class);

        $this->paymentInfo = $this->createMock(InfoInterface::class);

        $this->builder = new DeviceDataBuilder($this->subjectReader);
    }

    /**
     * @covers \PayPal\Braintree\Gateway\Request\PayPal\DeviceDataBuilder::build
     * @param array $paymentData
     * @param array $expected
     */
    #[DataProvider('buildDataProvider')]
    public function testBuild(array $paymentData, array $expected)
    {
        $subject = [
            'payment' => $this->paymentDataObject
        ];

        $this->subjectReader->expects(static::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($this->paymentDataObject);

        $this->paymentDataObject->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->paymentInfo);

        $this->paymentInfo->expects(static::once())
            ->method('getAdditionalInformation')
            ->willReturn($paymentData);

        $actual = $this->builder->build($subject);
        static::assertEquals($expected, $actual);
    }

    /**
     * Get variations for build method testing
     *
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [
                'paymentData' => [
                    'device_data' => '{correlation_id: 12s3jf9as}'
                ],
                'expected' => [
                    'deviceData' => '{correlation_id: 12s3jf9as}'
                ]
            ],
            [
                'paymentData' => [
                    'device_data' => null,
                ],
                'expected' => []
            ],
            [
                'paymentData' => [
                    'deviceData' => '{correlation_id: 12s3jf9as}',
                ],
                'expected' => []
            ],
            [
                'paymentData' => [],
                'expected' => []
            ]
        ];
    }
}

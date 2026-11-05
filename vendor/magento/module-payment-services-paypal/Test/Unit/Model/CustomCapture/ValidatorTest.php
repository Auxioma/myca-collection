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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\CustomCapture;

use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Eligibility;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Validator;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    /**
     * @var Validator
     */
    private Validator $validator;

    /**
     * @var Eligibility|MockObject
     */
    private Eligibility|MockObject $eligibility;

    /**
     * @var OrderInterface|MockObject
     */
    private OrderInterface|MockObject $order;

    protected function setUp(): void
    {
        $this->eligibility = $this->createMock(Eligibility::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->validator = new Validator($this->eligibility);
    }

    /**
     * @dataProvider emptyAmountProvider
     */
    public function testValidateAndCreateReturnsNullForEmptyAmount(mixed $amount): void
    {
        $this->assertNull($this->validator->validateAndCreate($amount, '', $this->order, true));
    }

    public static function emptyAmountProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    public function testValidateAndCreateRejectsNegativeAmount(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Additional Amount must not be negative.');

        $this->validator->validateAndCreate('-1', 'Reason', $this->order, true);
    }

    public function testValidateAndCreateRejectsOfflineCapture(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Additional Amount is only available for online capture.');

        $this->validator->validateAndCreate('10', 'Reason', $this->order, false);
    }

    public function testValidateAndCreateAllowsMissingReason(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn(1.0);

        $data = $this->validator->validateAndCreate('10', '', $this->order, true);

        $this->assertNotNull($data);
        $this->assertSame('', $data->getReason());
    }

    public function testValidateAndCreateRejectsIneligibleOrder(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(false);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Additional Amount is not available for this order.');

        $this->validator->validateAndCreate('10', 'Reason', $this->order, true);
    }

    public function testValidateAndCreateBuildsCustomCaptureData(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn(2.0);

        $data = $this->validator->validateAndCreate('30', "  Increased\nshipping  ", $this->order, true);

        $this->assertNotNull($data);
        $this->assertSame(30.0, $data->getAmount());
        $this->assertSame(15.0, $data->getBaseAmount());
        $this->assertSame('Increased shipping', $data->getReason());
    }

    public function testValidateAndCreateCanonicalizesOrderAndBaseAmounts(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn(3.0);
        $this->order->method('getBaseCurrencyCode')->willReturn('USD');
        $this->order->method('getOrderCurrencyCode')->willReturn('EUR');

        $data = $this->validator->validateAndCreate('10.005', 'Reason', $this->order, true);

        $this->assertNotNull($data);
        $this->assertSame(10.01, $data->getAmount());
        $this->assertSame(3.34, $data->getBaseAmount());
    }

    public function testValidateAndCreateRejectsPositiveAmountBelowCurrencyPrecision(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Additional Amount must be at least 0.01.');

        $this->validator->validateAndCreate('0.004', 'Reason', $this->order, true);
    }

    /**
     * @dataProvider sameCurrencyRateProvider
     */
    public function testValidateAndCreateUsesAmountForSameCurrencyRegardlessOfRate(mixed $rate): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn($rate);
        $this->order->method('getBaseCurrencyCode')->willReturn('USD');
        $this->order->method('getOrderCurrencyCode')->willReturn('USD');

        $data = $this->validator->validateAndCreate('30', 'Reason', $this->order, true);

        $this->assertNotNull($data);
        $this->assertSame(30.0, $data->getBaseAmount());
    }

    public static function sameCurrencyRateProvider(): array
    {
        return [
            'missing rate' => [null],
            'zero rate' => [0.0],
            'negative rate' => [-1.0],
            'non-finite rate' => [INF],
            'unexpected non-unit rate' => [2.0],
        ];
    }

    /**
     * @dataProvider invalidCurrencyRateProvider
     */
    public function testValidateAndCreateRejectsInvalidRateForDifferentCurrencies(mixed $rate): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn($rate);
        $this->order->method('getBaseCurrencyCode')->willReturn('USD');
        $this->order->method('getOrderCurrencyCode')->willReturn('EUR');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Cannot calculate the base Additional Amount because the currency rate is invalid.'
        );
        $this->validator->validateAndCreate('30', 'Reason', $this->order, true);
    }

    /**
     * @dataProvider invalidCurrencyRateProvider
     */
    public function testValidateAndCreateRejectsInvalidRateWhenCurrencyCannotBeEstablished(mixed $rate): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn($rate);
        $this->order->method('getBaseCurrencyCode')->willReturn(null);
        $this->order->method('getOrderCurrencyCode')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Cannot calculate the base Additional Amount because the currency rate is invalid.'
        );
        $this->validator->validateAndCreate('30', 'Reason', $this->order, true);
    }

    public static function invalidCurrencyRateProvider(): array
    {
        return [
            'missing rate' => [null],
            'zero rate' => [0.0],
            'negative rate' => [-1.0],
            'positive infinity' => [INF],
            'not a number' => [NAN],
        ];
    }

    public function testValidateAndCreateRejectsConversionOverflow(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn(PHP_FLOAT_MIN);
        $this->order->method('getBaseCurrencyCode')->willReturn('USD');
        $this->order->method('getOrderCurrencyCode')->willReturn('EUR');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Cannot calculate the base Additional Amount because the currency rate is invalid.'
        );
        $this->validator->validateAndCreate(PHP_FLOAT_MAX, 'Reason', $this->order, true);
    }

    public function testValidateAndCreateRejectsConversionUnderflow(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn(PHP_FLOAT_MAX);
        $this->order->method('getBaseCurrencyCode')->willReturn('USD');
        $this->order->method('getOrderCurrencyCode')->willReturn('EUR');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Cannot calculate the base Additional Amount because the currency rate is invalid.'
        );
        $this->validator->validateAndCreate(0.01, 'Reason', $this->order, true);
    }

    public function testValidateAndCreateRejectsNonNumericAmount(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Additional Amount must be a valid number.');

        $this->validator->validateAndCreate('abc', '', $this->order, true);
    }

    /**
     * @dataProvider nonFiniteAmountProvider
     */
    public function testValidateAndCreateRejectsNonFiniteAmount(mixed $amount): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Additional Amount must be a valid number.');

        $this->validator->validateAndCreate($amount, '', $this->order, true);
    }

    public static function nonFiniteAmountProvider(): array
    {
        return [
            'overflowing numeric string' => ['1e309'],
            'positive infinity' => [INF],
            'negative infinity' => [-INF],
            'not a number' => [NAN],
        ];
    }

    /**
     * @dataProvider zeroAmountProvider
     */
    public function testValidateAndCreateRejectsExplicitZeroAmount(mixed $amount): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Additional Amount must be at least 0.01.');

        $this->validator->validateAndCreate($amount, '', $this->order, true);
    }

    public static function zeroAmountProvider(): array
    {
        return [
            'zero' => ['0'],
            'decimal zero' => ['0.00'],
            'negative zero' => ['-0'],
        ];
    }

    public function testValidateAndCreateAcceptsMinimumAmount(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn(1.0);

        $data = $this->validator->validateAndCreate('0.01', '', $this->order, true);

        $this->assertNotNull($data);
        $this->assertSame(0.01, $data->getAmount());
        $this->assertSame(0.01, $data->getBaseAmount());
    }

    public function testValidateAndCreateRejectsLongReason(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Reason for Additional Amount must be 255 characters or fewer.');

        $this->validator->validateAndCreate('10', str_repeat('a', 256), $this->order, true);
    }

    public function testValidateAndCreateAcceptsReasonAtMultibyteCharacterLimit(): void
    {
        $reason = str_repeat('é', 255);
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->order->method('getBaseToOrderRate')->willReturn(1.0);

        $data = $this->validator->validateAndCreate('10', $reason, $this->order, true);

        $this->assertNotNull($data);
        $this->assertSame($reason, $data->getReason());
    }

    public function testValidateAndCreateRejectsReasonAboveMultibyteCharacterLimit(): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Reason for Additional Amount must be 255 characters or fewer.');

        $this->validator->validateAndCreate('10', str_repeat('é', 256), $this->order, true);
    }

    /**
     * @dataProvider invalidReasonTypeProvider
     */
    public function testValidateAndCreateRejectsInvalidReasonType(mixed $reason): void
    {
        $this->eligibility->method('isEligibleOrder')->willReturn(true);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Reason for Additional Amount must be a valid string.');

        $this->validator->validateAndCreate('10', $reason, $this->order, true);
    }

    public static function invalidReasonTypeProvider(): array
    {
        return [
            'array' => [[]],
            'object' => [new \stdClass()],
        ];
    }

    public function testNormalizeReasonRemovesHtmlTags(): void
    {
        $this->assertSame(
            'Increased shipping',
            $this->validator->normalizeReason("  <em>Increased</em>\n<b>shipping</b>  ")
        );
    }
}

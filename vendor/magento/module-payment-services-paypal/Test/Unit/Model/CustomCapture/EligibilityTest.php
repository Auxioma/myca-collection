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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\PaymentServicesPaypal\Model\ApmConfigProvider;
use Magento\PaymentServicesPaypal\Model\Config as PaypalConfig;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Eligibility;
use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EligibilityTest extends TestCase
{
    /**
     * @var Eligibility
     */
    private Eligibility $eligibility;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface|MockObject $scopeConfig;

    /**
     * @var BaseConfig|MockObject
     */
    private BaseConfig|MockObject $baseConfig;

    /**
     * @var PaypalConfig|MockObject
     */
    private PaypalConfig|MockObject $paypalConfig;

    /**
     * @var OrderInterface|MockObject
     */
    private OrderInterface|MockObject $order;

    /**
     * @var OrderPaymentInterface|MockObject
     */
    private OrderPaymentInterface|MockObject $payment;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->baseConfig = $this->createMock(BaseConfig::class);
        $this->paypalConfig = $this->createMock(PaypalConfig::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(OrderPaymentInterface::class);

        $this->eligibility = new Eligibility($this->scopeConfig, $this->baseConfig, $this->paypalConfig);
    }

    public function testIsEligibleOrderReturnsTrueForAuthorizedHostedFields(): void
    {
        $this->givenOverCaptureEnabled();
        $this->givenOrderPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->paypalConfig->expects($this->once())
            ->method('getPaymentIntent')
            ->with(HostedFieldsConfigProvider::CODE, 1)
            ->willReturn(MethodInterface::ACTION_AUTHORIZE);

        $this->assertTrue($this->eligibility->isEligibleOrder($this->order));
    }

    public function testIsEligibleOrderReturnsFalseWhenOverCaptureIsDisabled(): void
    {
        $this->order->method('getStoreId')->willReturn(1);
        $this->baseConfig->method('isEnabled')->with(1)->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn('0');
        $this->paypalConfig->expects($this->never())->method('getPaymentIntent');

        $this->assertFalse($this->eligibility->isEligibleOrder($this->order));
    }

    public function testIsEligibleOrderReturnsFalseWhenMethodIsApm(): void
    {
        $this->givenOverCaptureEnabled();
        $this->givenOrderPaymentMethod(ApmConfigProvider::CODE);
        $this->paypalConfig->expects($this->never())->method('getPaymentIntent');

        $this->assertFalse($this->eligibility->isEligibleOrder($this->order));
    }

    public function testIsEligibleOrderUsesHostedFieldsActionForVault(): void
    {
        $this->givenOverCaptureEnabled();
        $this->givenOrderPaymentMethod(HostedFieldsConfigProvider::CC_VAULT_CODE);
        $this->paypalConfig->expects($this->once())
            ->method('getPaymentIntent')
            ->with(HostedFieldsConfigProvider::CODE, 1)
            ->willReturn(MethodInterface::ACTION_AUTHORIZE);

        $this->assertTrue($this->eligibility->isEligibleOrder($this->order));
    }

    public function testIsEligibleOrderReturnsFalseWhenPaymentActionIsCapture(): void
    {
        $this->givenOverCaptureEnabled();
        $this->givenOrderPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->paypalConfig->method('getPaymentIntent')->willReturn('capture');

        $this->assertFalse($this->eligibility->isEligibleOrder($this->order));
    }

    public function testIsEligibleOrderReturnsFalseWhenPaymentIsMissing(): void
    {
        $this->givenOverCaptureEnabled();
        $this->order->method('getPayment')->willReturn(null);
        $this->paypalConfig->expects($this->never())->method('getPaymentIntent');

        $this->assertFalse($this->eligibility->isEligibleOrder($this->order));
    }

    public function testIsEligibleOrderPassesNullStoreIdToConfigurationAndPaymentIntent(): void
    {
        $this->order->method('getStoreId')->willReturn(null);
        $this->baseConfig->expects($this->once())->method('isEnabled')->with(null)->willReturn(true);
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                Eligibility::CONFIG_PATH_OVER_CAPTURE_ENABLED,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('1');
        $this->givenOrderPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->paypalConfig->expects($this->once())
            ->method('getPaymentIntent')
            ->with(HostedFieldsConfigProvider::CODE, null)
            ->willReturn(MethodInterface::ACTION_AUTHORIZE);

        $this->assertTrue($this->eligibility->isEligibleOrder($this->order));
    }

    public function testIsEligibleOrderReturnsFalseWhenPaymentIntentStoreDoesNotExist(): void
    {
        $this->givenOverCaptureEnabled();
        $this->givenOrderPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->paypalConfig->expects($this->once())
            ->method('getPaymentIntent')
            ->willThrowException(new NoSuchEntityException(__('Store does not exist.')));

        $this->assertFalse($this->eligibility->isEligibleOrder($this->order));
    }

    private function givenOverCaptureEnabled(): void
    {
        $this->order->method('getStoreId')->willReturn(1);
        $this->baseConfig->method('isEnabled')->with(1)->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn('1');
    }

    private function givenOrderPaymentMethod(string $method): void
    {
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->payment->method('getMethod')->willReturn($method);
    }
}

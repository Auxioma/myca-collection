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

use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\PaymentServicesPaypal\Plugin\Vault\Method;
use Magento\Quote\Model\Quote;
use Magento\Vault\Model\Method\Vault;
use Magento\Vault\Model\PaymentTokenManagement;
use PHPUnit\Framework\TestCase;

class MethodTest extends TestCase
{
    public function testAfterIsAvailableWithoutQuotePreservesResult(): void
    {
        $subject = $this->createMock(Vault::class);
        $subject->expects($this->never())->method('getCode');
        $tokenManagement = $this->createMock(PaymentTokenManagement::class);
        $tokenManagement->expects($this->never())->method('getVisibleAvailableTokens');
        $plugin = new Method($tokenManagement);

        foreach ([true, false] as $result) {
            $this->assertSame($result, $plugin->afterIsAvailable($subject, $result));
            $this->assertSame($result, $plugin->afterIsAvailable($subject, $result, null));
        }
    }

    public function testAfterIsAvailableWithGuestQuoteRemainsUnavailable(): void
    {
        $subject = $this->createMock(Vault::class);
        $subject->method('getCode')->willReturn(HostedFieldsConfigProvider::CC_VAULT_CODE);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerId'])
            ->getMock();
        $quote->expects($this->once())->method('getCustomerId')->willReturn(null);
        $tokenManagement = $this->createMock(PaymentTokenManagement::class);
        $tokenManagement->expects($this->never())->method('getVisibleAvailableTokens');
        $plugin = new Method($tokenManagement);

        $this->assertFalse($plugin->afterIsAvailable($subject, true, $quote));
    }

    public function testAfterIsAvailableWithCustomerQuoteChecksTokens(): void
    {
        $subject = $this->createMock(Vault::class);
        $subject->method('getCode')->willReturn(HostedFieldsConfigProvider::CC_VAULT_CODE);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerId'])
            ->getMock();
        $quote->method('getCustomerId')->willReturn(42);
        $tokenManagement = $this->createMock(PaymentTokenManagement::class);
        $tokenManagement->expects($this->once())
            ->method('getVisibleAvailableTokens')
            ->with(42)
            ->willReturn([]);
        $plugin = new Method($tokenManagement);

        $this->assertFalse($plugin->afterIsAvailable($subject, true, $quote));
    }
}

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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ConfigTest extends TestCase
{
    private const CONFIG_PATH =
        'payment/payment_services_paypal_smart_buttons/calculate_tax_for_all_shipping_methods';

    public function testCalculateTaxForAllShippingMethodsIsDisabled(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(self::CONFIG_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('0');

        $this->assertFalse(
            $this->createConfig($scopeConfig)->isCalculateTaxForAllShippingMethodsEnabled()
        );
    }

    public function testCalculateTaxForAllShippingMethodsUsesStoreScope(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(self::CONFIG_PATH, ScopeInterface::SCOPE_STORE, 7)
            ->willReturn('1');

        $this->assertTrue(
            $this->createConfig($scopeConfig)->isCalculateTaxForAllShippingMethodsEnabled(7)
        );
    }

    private function createConfig(ScopeConfigInterface $scopeConfig): Config
    {
        return new Config(
            $this->createMock(BaseConfig::class),
            $scopeConfig,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Repository::class),
            $this->createMock(RequestInterface::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }
}

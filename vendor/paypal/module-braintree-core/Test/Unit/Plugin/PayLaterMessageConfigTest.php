<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

namespace PayPal\Braintree\Test\Unit\Plugin;

use Magento\Checkout\Block\Cart\Sidebar;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Gateway\Config\PayPalPayLater\Config as PayLaterConfig;
use PayPal\Braintree\Model\Ui\ConfigProvider;
use PayPal\Braintree\Plugin\PayLaterMessageConfig;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PayLaterMessageConfigTest extends TestCase
{
    /** @var Config|MockObject */
    private Config|MockObject $configMock;

    /** @var ConfigProvider|MockObject */
    private ConfigProvider|MockObject $configProviderMock;

    /** @var PayLaterConfig|MockObject */
    private PayLaterConfig|MockObject $payLaterConfigMock;

    /** @var StoreManagerInterface|MockObject */
    private StoreManagerInterface|MockObject $storeManagerMock;

    /** @var Sidebar|MockObject */
    private Sidebar|MockObject $sidebarMock;

    /** @var PayLaterMessageConfig */
    private PayLaterMessageConfig $payLaterMessageConfig;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->configProviderMock = $this->createMock(ConfigProvider::class);
        $this->payLaterConfigMock = $this->createMock(PayLaterConfig::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->sidebarMock = $this->createMock(Sidebar::class);

        $this->payLaterMessageConfig = new PayLaterMessageConfig(
            $this->configMock,
            $this->configProviderMock,
            $this->payLaterConfigMock,
            $this->storeManagerMock
        );
    }

    /**
     * Test when PayPal is not active: result should remain unchanged.
     *
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function testAfterGetConfigWhenPayPalIsNotActive(): void
    {
        $originalResult = ['test' => 'test_value'];

        $this->configMock->method('isActive')->willReturn(false);

        $result = $this->payLaterMessageConfig->afterGetConfig($this->sidebarMock, $originalResult);

        $this->assertSame($originalResult, $result);
    }

    /**
     * Test when PayPal is active but Pay Later message is not active.
     *
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function testAfterGetConfigWhenPayPalActiveButPayLaterNotActive(): void
    {
        $originalResult = [];

        $this->configMock->method('isActive')
            ->willReturn(true);
        $this->payLaterConfigMock->method('isMessageActive')
            ->with('cart')
            ->willReturn(false);

        $result = $this->payLaterMessageConfig->afterGetConfig($this->sidebarMock, $originalResult);

        $this->assertSame($originalResult, $result);
    }

    /**
     * Test when PayPal and Pay Later message are both active.
     *
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function testAfterGetConfigWhenPayPalAndPayLaterAreActive(): void
    {
        $originalResult = [];

        $messageStyles = [
            'layout' => 'text',
            'logo' => [
                'type' => 'inline',
                'position' => 'left'
            ],
            'text' => [
                'color' => 'black'
            ]
        ];

        $this->configMock->method('isActive')
            ->willReturn(true);

        $this->configProviderMock->method('getClientToken')
            ->willReturn('token123braintree456test');

        $this->payLaterConfigMock->method('isMessageActive')
            ->with('cart')
            ->willReturn(true);

        $this->configMock->method('getMessageStyles')
            ->with('cart')
            ->willReturn($messageStyles);

        $storeMock = $this->createMock(Store::class);
        $storeMock->method('getCurrentCurrencyCode')
            ->willReturn('USD');
        $this->storeManagerMock->method('getStore')
            ->willReturn($storeMock);

        $result = $this->payLaterMessageConfig->afterGetConfig($this->sidebarMock, $originalResult);

        $this->assertEquals('token123braintree456test', $result['payPalBraintreeClientToken']);
        $this->assertEquals($messageStyles, $result['payPalBraintreePaylaterMessageConfig']);
        $this->assertEquals('USD', $result['paypalBraintreeCurrencyCode']);
    }
}

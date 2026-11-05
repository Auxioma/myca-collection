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
namespace PayPal\Braintree\Test\Unit\Model\Ui\PayPal;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Gateway\Config\PayPalCredit\Config as CreditConfig;
use PayPal\Braintree\Model\Ui\PayPal\ConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

/**
 * Test for class \PayPal\Braintree\Model\Ui\PayPal\ConfigProvider
 */
class ConfigProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var ResolverInterface|MockObject
     */
    private $localeResolver;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var CreditConfig|MockObject
     */
    private $creditConfig;

    protected function setUp(): void
    {
        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->creditConfig = $this->getMockBuilder(CreditConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->localeResolver = $this->createMock(ResolverInterface::class);
    }

    /**
     * Run test getConfig method
     *
     * @param array $expected
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    #[DataProvider('getConfigDataProvider')]
    public function testGetConfig(array $expected)
    {
        $this->markTestSkipped('Skip this test');
        $this->configProvider = new ConfigProvider(
            $this->config,
            $this->creditConfig,
            $this->localeResolver
        );
        $this->config->method('isActive')
            ->willReturn(true);

        $this->config->method('isAllowToEditShippingAddress')
            ->willReturn(true);

        $this->config->method('getMerchantName')
            ->willReturn('Test');

        $this->config->method('getTitle')
            ->willReturn('Payment Title');

        $this->localeResolver->method('getLocale')
            ->willReturn('en_US');

        $this->config->method('getPayPalIcon')
            ->willReturn([
                'width' => 30, 'height' => 26, 'url' => 'https://icon.test.url'
            ]);

        self::assertEquals($expected, $this->configProvider->getConfig());
    }

    /**
     * @return array
     */
    public static function getConfigDataProvider(): array
    {
        $payPalPaymentMarkSrc = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-medium.png';
        $creditPaymentMarkSrc = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-medium.png';

        return [
            [
                'expected' => [
                    'payment' => [
                        ConfigProvider::PAYPAL_CODE => [
                            'isActive' => true,
                            'title' => 'Payment Title',
                            'isAllowShippingAddressOverride' => true,
                            'merchantName' => 'Test',
                            'payeeEmail' => null,
                            'locale' => 'en_US',
                            'paymentAcceptanceMarkSrc' => $payPalPaymentMarkSrc,
                            'vaultCode' => ConfigProvider::PAYPAL_VAULT_CODE,
                            'paymentIcon' => [
                                'width' => 30, 'height' => 26, 'url' => 'https://icon.test.url'
                            ],
                            'style' => [
                                'shape' => null,
                                'color' => null
                            ]
                        ],

                        ConfigProvider::PAYPAL_CREDIT_CODE => [
                            'isActive' => null,
                            'title' => __('PayPal Credit'),
                            'isAllowShippingAddressOverride' => true,
                            'merchantName' => 'Test',
                            'payeeEmail' => null,
                            'locale' => 'en_US',
                            'paymentAcceptanceMarkSrc' => $creditPaymentMarkSrc,
                            'paymentIcon' => [
                                'width' => 30, 'height' => 26, 'url' => 'https://icon.test.url'
                            ],
                            'style' => [
                                'shape' => null,
                                'color' => null
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}

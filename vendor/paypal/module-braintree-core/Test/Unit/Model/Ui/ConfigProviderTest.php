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
namespace PayPal\Braintree\Test\Unit\Model\Ui;

use Magento\Framework\Filesystem\Io\File;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Ui\ConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject as MockObject;
use PayPal\Braintree\Gateway\Config\PayPal\Config as PayPalConfig;
use Magento\Payment\Model\CcConfig;
use Magento\Framework\View\Asset\Source;

/**
 * Test for class \PayPal\Braintree\Model\Ui\ConfigProvider
 */
class ConfigProviderTest extends TestCase
{
    private const CLIENT_TOKEN = 'token';
    private const MERCHANT_ACCOUNT_ID = '245345';

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $config;

    /**
     * @var BraintreeAdapter|MockObject
     */
    private BraintreeAdapter|MockObject $braintreeAdapter;

    /**
     * @var ConfigProvider|MockObject
     */
    private ConfigProvider|MockObject $configProvider;

    /**
     * @var PayPalConfig|MockObject
     */
    private PayPalConfig|MockObject $payPalConfig;

    /**
     * @var CcConfig|MockObject
     */
    private CcConfig|MockObject $ccConfig;

    /**
     * @var Source|MockObject
     */
    private Source|MockObject $assetSource;

    /**
     * @var File|MockObject
     */
    private File|MockObject $fileIo;

    protected function setUp(): void
    {
        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->payPalConfig = $this->getMockBuilder(PayPalConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->braintreeAdapter = $this->getMockBuilder(BraintreeAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->ccConfig = $this->getMockBuilder(CcConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->assetSource = $this->getMockBuilder(Source::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->fileIo = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configProvider = new ConfigProvider(
            $this->config,
            $this->payPalConfig,
            $this->braintreeAdapter,
            $this->ccConfig,
            $this->assetSource,
            $this->fileIo
        );
    }

    /**
     * Run test getConfig method
     *
     * @param array $config
     * @param array $payPalConfig
     * @param array $expected
     */
    #[DataProvider('getConfigDataProvider')]
    public function testGetConfig(array $config, array $payPalConfig, array $expected)
    {
        $this->braintreeAdapter->expects(static::once())
            ->method('generate')
            ->willReturn(self::CLIENT_TOKEN);

        foreach ($config as $method => $value) {
            $this->config->method($method)
                ->willReturn($value);
        }

        // getMerchantAccountId is called inside getClientToken
        $this->config->method('getMerchantAccountId')
            ->willReturn('');

        foreach ($payPalConfig as $method => $returnValue) {
            $this->payPalConfig->expects(static::once())
                ->method($method)
                ->willReturn($returnValue);
        }

        $this->ccConfig->expects(static::once())
            ->method('getCcAvailableTypes')
            ->willReturn([]);

        static::assertEquals($expected, $this->configProvider->getConfig());
    }

    /**
     * Run test getClientToken method
     *
     * @covers \PayPal\Braintree\Model\Ui\ConfigProvider::getClientToken
     */
    #[DataProvider('getClientTokenDataProvider')]
    public function testGetClientToken($merchantAccountId, $params)
    {
        $this->config->expects(static::once())
            ->method('getMerchantAccountId')
            ->willReturn($merchantAccountId);

        $this->braintreeAdapter->expects(static::once())
            ->method('generate')
            ->with($params)
            ->willReturn(self::CLIENT_TOKEN);

        static::assertEquals(self::CLIENT_TOKEN, $this->configProvider->getClientToken());
    }

    /**
     * @return array
     */
    public static function getConfigDataProvider(): array
    {
        return [
            [
                'config' => [
                    'isActive' => true,
                    'getCcTypesMapper' => ['visa' => 'VI', 'american-express'=> 'AE'],
                    'getCountrySpecificCardTypeConfig' => [
                        'GB' => ['VI', 'AE'],
                        'US' => ['DI', 'JCB']
                    ],
                    'getAvailableCardTypes' => ['AE', 'VI', 'MC', 'DI', 'JCB'],
                    'isCvvEnabled' => true,
                    'getEnvironment' => 'test-environment',
                    'getMerchantId' => 'test-merchant-id',
                ],
                'payPalConfig' => [
                    'getButtonShape' => 'pill',
                    'getButtonColor' => 'blue',
                    'isFundingOptionCardDisabled' => false,
                    'isFundingOptionElvDisabled' => false
                ],
                'expected' => [
                    'payment' => [
                        ConfigProvider::CODE => [
                            'isActive' => true,
                            'clientToken' => self::CLIENT_TOKEN,
                            'ccTypesMapper' => ['visa' => 'VI', 'american-express' => 'AE'],
                            'countrySpecificCardTypes' => [
                                'GB' => ['VI', 'AE'],
                                'US' => ['DI', 'JCB']
                            ],
                            'availableCardTypes' => ['AE', 'VI', 'MC', 'DI', 'JCB'],
                            'useCvv' => true,
                            'environment' => 'test-environment',
                            'merchantId' => 'test-merchant-id',
                            'ccVaultCode' => ConfigProvider::CC_VAULT_CODE,
                            'style' => [
                                'shape' => 'pill',
                                'color' => 'blue'
                            ],
                            'disabledFunding' => [
                                'card' => false,
                                'elv' => false
                            ],
                            'icons' => []
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public static function getClientTokenDataProvider(): array
    {
        return [
            [
                'merchantAccountId' => '',
                'params' => []
            ],
            [
                'merchantAccountId' => self::MERCHANT_ACCOUNT_ID,
                'params' => ['merchantAccountId' => self::MERCHANT_ACCOUNT_ID]
            ]
        ];
    }
}

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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\Api;

use Magento\PaymentServicesPaypal\Model\Api\Data\PaymentConfigSmartButtonsLogo;
use Magento\PaymentServicesPaypal\Model\Api\Data\PaymentConfigSmartButtonsMessageStyles;
use Magento\PaymentServicesPaypal\Model\Api\Data\PaymentConfigSmartButtonsText;
use Magento\PaymentServicesPaypal\Model\Api\PaymentConfigManagement;
use Magento\PaymentServicesPaypal\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PaymentConfigManagementTest extends TestCase
{
    private const STORE_ID = 1;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var PaymentConfigManagement
     */
    private PaymentConfigManagement $paymentConfigManagement;

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->paymentConfigManagement = (new ReflectionClass(PaymentConfigManagement::class))
            ->newInstanceWithoutConstructor();

        $this->setPrivateProperty('config', $this->config);
        $this->setPrivateProperty('messageStyles', $this->getDefaultMessageStyles());
        $this->setPrivateProperty('payLaterStylesByStore', []);
        $this->setPrivateProperty('paymentConfigSmartButtonsMessageStylesFactory', new class {
            public function create(): PaymentConfigSmartButtonsMessageStyles
            {
                return new PaymentConfigSmartButtonsMessageStyles();
            }
        });
        $this->setPrivateProperty('paymentConfigSmartButtonsLogoFactory', new class {
            public function create(): PaymentConfigSmartButtonsLogo
            {
                return new PaymentConfigSmartButtonsLogo();
            }
        });
        $this->setPrivateProperty('paymentConfigSmartButtonsTextFactory', new class {
            public function create(): PaymentConfigSmartButtonsText
            {
                return new PaymentConfigSmartButtonsText();
            }
        });
    }

    /**
     * @dataProvider mappedLocationProvider
     *
     * @param string $requestedLocation
     * @param string $configLocation
     * @return void
     */
    public function testPayLaterMessageStylesUseMappedLocationConfig(
        string $requestedLocation,
        string $configLocation
    ): void {
        $this->config->expects($this->once())
            ->method('getPayLaterStyling')
            ->with(self::STORE_ID)
            ->willReturn(json_encode(self::getPayLaterMessageConfig()));

        $messageStyles = $this->getPayLaterMessageStylesConfig($requestedLocation);
        $logo = $messageStyles->getLogo();
        $text = $messageStyles->getText();

        $this->assertSame($configLocation . '_layout', $messageStyles->getLayout());
        $this->assertInstanceOf(PaymentConfigSmartButtonsLogo::class, $logo);
        $this->assertSame($configLocation . '_logo', $logo->getType());
        $this->assertSame($configLocation . '_position', $logo->getPosition());
        $this->assertInstanceOf(PaymentConfigSmartButtonsText::class, $text);
        $this->assertSame($configLocation . '_align', $text->getAlign());
        $this->assertSame($configLocation . '_color', $text->getColor());
        $this->assertSame($this->getExpectedTextSize($configLocation), $text->getSize());
        $this->assertIsInt($text->getData('size'));
    }

    /**
     * @return array
     */
    public static function mappedLocationProvider(): array
    {
        return [
            'product detail' => ['product_detail', 'product'],
            'minicart' => ['minicart', 'cart'],
            'cart' => ['cart', 'cart'],
            'checkout' => ['checkout', 'checkout'],
            'start of checkout' => ['start_of_checkout', 'checkout'],
        ];
    }

    /**
     * @dataProvider missingStylesProvider
     *
     * @param string|null $rawStyles
     * @param string $location
     * @return void
     */
    public function testPayLaterMessageStylesAreNullWhenLocationConfigCannotBeUsed(
        ?string $rawStyles,
        string $location
    ): void {
        $this->config->expects($this->once())
            ->method('getPayLaterStyling')
            ->with(self::STORE_ID)
            ->willReturn($rawStyles);

        $messageStyles = $this->getPayLaterMessageStylesConfig($location);

        $this->assertNull($messageStyles);
    }

    /**
     * @return array
     */
    public static function missingStylesProvider(): array
    {
        return [
            'empty config' => ['', 'product_detail'],
            'invalid JSON' => ['not-json', 'product_detail'],
            'scalar JSON' => ['"not-an-array"', 'product_detail'],
            'scalar location styles' => [json_encode(['product' => 'not-an-array']), 'product_detail'],
            'unknown location' => [json_encode(self::getPayLaterMessageConfig()), 'admin'],
        ];
    }

    /**
     * @return void
     */
    public function testPayLaterMessageStylesCanOmitLogoAndText(): void
    {
        $this->config->expects($this->once())
            ->method('getPayLaterStyling')
            ->with(self::STORE_ID)
            ->willReturn(json_encode(['product' => ['layout' => 'text']]));

        $messageStyles = $this->getPayLaterMessageStylesConfig('product_detail');

        $this->assertSame('text', $messageStyles->getLayout());
        $this->assertNull($messageStyles->getLogo());
        $this->assertNull($messageStyles->getText());
    }

    /**
     * @return void
     */
    public function testPayLaterMessageStylesCanUsePartialTextConfig(): void
    {
        $this->config->expects($this->once())
            ->method('getPayLaterStyling')
            ->with(self::STORE_ID)
            ->willReturn(json_encode([
                'checkout' => [
                    'layout' => 'text',
                    'text' => [
                        'color' => 'white',
                    ],
                ],
            ]));

        $messageStyles = $this->getPayLaterMessageStylesConfig('checkout');
        $text = $messageStyles->getText();

        $this->assertSame('text', $messageStyles->getLayout());
        $this->assertInstanceOf(PaymentConfigSmartButtonsText::class, $text);
        $this->assertNull($text->getAlign());
        $this->assertSame('white', $text->getColor());
        $this->assertNull($text->getSize());
    }

    /**
     * @return void
     */
    public function testPayLaterMessageStylesAreDecodedOncePerStore(): void
    {
        $this->config->expects($this->once())
            ->method('getPayLaterStyling')
            ->with(self::STORE_ID)
            ->willReturn(json_encode(self::getPayLaterMessageConfig()));

        $productMessageStyles = $this->getPayLaterMessageStylesConfig('product_detail');
        $cartMessageStyles = $this->getPayLaterMessageStylesConfig('cart');

        $this->assertSame('product_layout', $productMessageStyles->getLayout());
        $this->assertSame('cart_layout', $cartMessageStyles->getLayout());
    }

    /**
     * @return void
     */
    public function testMissingStylesAreDecodedOncePerStore(): void
    {
        $this->config->expects($this->once())
            ->method('getPayLaterStyling')
            ->with(self::STORE_ID)
            ->willReturn('');

        $firstMessageStyles = $this->getPayLaterMessageStylesConfig('product_detail');
        $secondMessageStyles = $this->getPayLaterMessageStylesConfig('cart');

        $this->assertNull($firstMessageStyles);
        $this->assertNull($secondMessageStyles);
    }

    /**
     * @param string $location
     * @return PaymentConfigSmartButtonsMessageStyles|null
     */
    private function getPayLaterMessageStylesConfig(string $location): ?PaymentConfigSmartButtonsMessageStyles
    {
        $reflectionClass = new ReflectionClass(PaymentConfigManagement::class);
        $method = $reflectionClass->getMethod('getPayLaterMessageStylesConfig');

        return $method->invoke($this->paymentConfigManagement, $location, self::STORE_ID);
    }

    /**
     * @param string $propertyName
     * @param mixed $value
     * @return void
     */
    private function setPrivateProperty(string $propertyName, $value): void
    {
        $reflectionClass = new ReflectionClass(PaymentConfigManagement::class);
        $property = $reflectionClass->getProperty($propertyName);
        $property->setValue($this->paymentConfigManagement, $value);
    }

    /**
     * @return array
     */
    private function getDefaultMessageStyles(): array
    {
        $logo = new PaymentConfigSmartButtonsLogo();
        $logo->setType('inline');

        return [
            'layout' => 'text',
            'logo' => $logo,
            'text' => [
                'align' => 'left',
                'color' => 'black',
                'size' => '12',
            ],
        ];
    }

    /**
     * @return array
     */
    private static function getPayLaterMessageConfig(): array
    {
        return [
            'product' => self::getPayLaterLocationStyles('product', 13),
            'cart' => self::getPayLaterLocationStyles('cart', 14),
            'checkout' => self::getPayLaterLocationStyles('checkout', 15),
        ];
    }

    /**
     * @param string $location
     * @param int $size
     * @return array
     */
    private static function getPayLaterLocationStyles(string $location, int $size): array
    {
        return [
            'layout' => $location . '_layout',
            'logo' => [
                'type' => $location . '_logo',
                'position' => $location . '_position',
            ],
            'text' => [
                'align' => $location . '_align',
                'color' => $location . '_color',
                'size' => (string) $size,
            ],
        ];
    }

    /**
     * @param string $location
     * @return int
     */
    private function getExpectedTextSize(string $location): int
    {
        $sizes = [
            'product' => 13,
            'cart' => 14,
            'checkout' => 15,
        ];

        return $sizes[$location];
    }
}

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
namespace PayPal\Braintree\Test\Unit\Gateway\Request;

use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Request\DescriptorDataBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

class DescriptorDataBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config|MockObject
     */
    private Config|MockObject $config;

    /**
     * @var DescriptorDataBuilder
     */
    private DescriptorDataBuilder $builder;

    protected function setUp(): void
    {
        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDynamicDescriptors'])
            ->getMock();

        $this->builder = new DescriptorDataBuilder($this->config);
    }

    /**
     * @covers \PayPal\Braintree\Gateway\Request\DescriptorDataBuilder::build
     * @param array $descriptors
     * @param array $expected
     */
    #[DataProvider('buildDataProvider')]
    public function testBuild(array $descriptors, array $expected)
    {
        $this->config->expects(static::once())
            ->method('getDynamicDescriptors')
            ->willReturn($descriptors);

        $actual = $this->builder->build([]);
        static::assertEquals($expected, $actual);
    }

    /**
     * Get variations for build method testing
     * @return array
     */
    public static function buildDataProvider(): array
    {
        $name = 'company * product';
        $phone = '333-22-22-333';
        $url = 'https://test.url.mage.com';
        return [
            [
                'descriptors' => [
                    'name' => $name,
                    'phone' => $phone,
                    'url' => $url
                ],
                'expected' => [
                    'descriptor' => [
                        'name' => $name,
                        'phone' => $phone,
                        'url' => $url
                    ]
                ]
            ],
            [
                'descriptors' => [
                    'name' => $name,
                    'phone' => $phone
                ],
                'expected' => [
                    'descriptor' => [
                        'name' => $name,
                        'phone' => $phone
                    ]
                ]
            ],
            [
                'descriptors' => [
                    'name' => $name
                ],
                'expected' => [
                    'descriptor' => [
                        'name' => $name
                    ]
                ]
            ],
            [
                'descriptors' => [],
                'expected' => []
            ]
        ];
    }
}

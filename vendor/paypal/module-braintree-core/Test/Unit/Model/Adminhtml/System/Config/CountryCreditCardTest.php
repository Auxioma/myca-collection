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

namespace PayPal\Braintree\Test\Unit\Model\Adminhtml\System\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PayPal\Braintree\Model\Adminhtml\System\Config\CountryCreditCard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class CountryCreditCardTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CountryCreditCard
     */
    protected CountryCreditCard $model;

    /**
     * @var ObjectManager
     */
    protected ObjectManager $objectManager;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    protected ScopeConfigInterface|MockObject $resourceMock;

    /**
     * @var Random|MockObject
     */
    protected Random|MockObject $mathRandomMock;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $serializerMock;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(AbstractResource::class);
        $this->mathRandomMock = $this->getMockBuilder(Random::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->serializerMock = $this->createMock(Json::class);

        $this->objectManager = new ObjectManager($this);
        $this->model = $this->objectManager->getObject(
            CountryCreditCard::class,
            [
                'mathRandom' => $this->mathRandomMock,
                'resource' => $this->resourceMock,
                'serializer' => $this->serializerMock
            ]
        );
    }

    /**
     * @param array $value
     * @param array $expectedValue
     * @param string $encodedValue
     */
    #[DataProvider('beforeSaveDataProvider')]
    public function testBeforeSave(array $value, array $expectedValue, string $encodedValue)
    {
        $this->model->setValue($value);

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->with($expectedValue)
            ->willReturn($encodedValue);

        $this->model->beforeSave();
        $this->assertEquals($encodedValue, $this->model->getValue());
    }

    /**
     * Get data for testing credit card types
     * @return array
     */
    public static function beforeSaveDataProvider(): array
    {
        return [
            'empty_value' => [
                [],
                [],
                '[]'
            ],
            'not_array' => [
                ['US'],
                [],
                '[]'
            ],
            'array_with_invalid_format' => [
                [
                    [
                        'country_id' => 'US',
                    ],
                ],
                [],
                '[]'
            ],
            'array_with_two_countries' => [
                [
                    [
                        'country_id' => 'AF',
                        'cc_types' => ['AE', 'VI']
                    ],
                    [
                        'country_id' => 'US',
                        'cc_types' => ['AE', 'VI', 'MA']
                    ],
                    '__empty' => "",
                ],
                [
                    'AF' => ['AE', 'VI'],
                    'US' => ['AE', 'VI', 'MA'],
                ],
                '{"AF":["AE","VI"],"US":["AE","VI","MA"]}'
            ],
            'array_with_two_same_countries' => [
                [
                    [
                        'country_id' => 'AF',
                        'cc_types' => ['AE', 'VI']
                    ],
                    [
                        'country_id' => 'US',
                        'cc_types' => ['AE', 'VI', 'MA']
                    ],
                    [
                        'country_id' => 'US',
                        'cc_types' => ['VI', 'OT']
                    ],
                    '__empty' => "",
                ],
                [
                    'AF' => ['AE', 'VI'],
                    'US' => ['AE', 'VI', 'MA', 'OT'],
                ],
                '{"AF":["AE","VI"],"US":["AE","VI","MA","OT"]}'
            ],
        ];
    }

    /**
     * @param string $encodedValue
     * @param array|null $value
     * @param array $hashData
     * @param array|null $expected
     * @param int $unserializeCalledNum
     * @throws LocalizedException
     */
    #[DataProvider('afterLoadDataProvider')]
    public function testAfterLoad(
        string $encodedValue,
        ?array $value,
        array  $hashData,
        ?array $expected,
        int $unserializeCalledNum = 1
    ) {
        $this->markTestSkipped('Skip this test');
        $this->model->setValue($encodedValue);
        $index = 0;
        foreach ($hashData as $hash) {
            $this->mathRandomMock->expects($this->at($index))
                ->method('getUniqueHash')
                ->willReturn($hash);
            $index++;
        }

        $this->serializerMock->expects($this->exactly($unserializeCalledNum))
            ->method('unserialize')
            ->with($encodedValue)
            ->willReturn($value);

        $this->model->afterLoad();
        $this->assertEquals($expected, $this->model->getValue());
    }

    /**
     * Get data to test saved credit cards types
     *
     * @return array
     */
    public static function afterLoadDataProvider(): array
    {
        return [
            'empty' => [
                '[]',
                [],
                [],
                []
            ],
            'null' => [
                '',
                null,
                [],
                null,
                0
            ],
            'valid data' => [
                '{"US":["AE","VI","MA"],"AF":["AE","MA"]}',
                [
                    'US' => ['AE', 'VI', 'MA'],
                    'AF' => ['AE', 'MA']
                ],
                ['hash_1', 'hash_2'],
                [
                    'hash_1' => [
                        'country_id' => 'US',
                        'cc_types' => ['AE', 'VI', 'MA']
                    ],
                    'hash_2' => [
                        'country_id' => 'AF',
                        'cc_types' => ['AE', 'MA']
                    ]
                ]
            ]
        ];
    }
}

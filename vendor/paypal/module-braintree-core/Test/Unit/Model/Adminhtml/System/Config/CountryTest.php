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

use Magento\Directory\Model\ResourceModel\Country\Collection;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PayPal\Braintree\Model\Adminhtml\System\Config\Country;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class CountryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Country
     */
    protected Country $model;

    /**
     * @var Collection|MockObject
     */
    protected Collection|MockObject $countryCollectionMock;

    /**
     * @var ObjectManager
     */
    protected ObjectManager $objectManager;

    protected function setUp(): void
    {
        $this->countryCollectionMock = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager = new ObjectManager($this);
        $this->model = $this->objectManager->getObject(
            Country::class,
            [
                'countryCollection' => $this->countryCollectionMock,
            ]
        );
    }

    /**
     * @covers \PayPal\Braintree\Model\Adminhtml\System\Config\Country::toOptionArray
     */
    public function testToOptionArrayMultiSelect()
    {
        $countries = [
            [
                'value' => 'US',
                'label' => 'United States',
            ],
            [
                'value' => 'UK',
                'label' => 'United Kingdom',
            ],
        ];
        $this->initCountryCollectionMock($countries);

        $this->assertEquals($countries, $this->model->toOptionArray(true));
    }

    /**
     * @covers \PayPal\Braintree\Model\Adminhtml\System\Config\Country::toOptionArray
     */
    public function testToOptionArray()
    {
        $countries = [
            [
                'value' => 'US',
                'label' => 'United States',
            ],
            [
                'value' => 'UK',
                'label' => 'United Kingdom',
            ],
        ];
        $this->initCountryCollectionMock($countries);

        $header = ['value' => '', 'label' => new Phrase('--Please Select--')];
        array_unshift($countries, $header);

        $this->assertEquals($countries, $this->model->toOptionArray());
    }

    /**
     * @covers \PayPal\Braintree\Model\Adminhtml\System\Config\Country::isCountryRestricted
     * @param string $countryId
     */
    #[DataProvider('countryDataProvider')]
    public function testIsCountryRestricted(string $countryId)
    {
        static::assertTrue($this->model->isCountryRestricted($countryId));
    }

    /**
     * Get simple list of not available braintree countries
     * @return array
     */
    public static function countryDataProvider(): array
    {
        return [
            ['MM'],
            ['IR'],
            ['SD'],
            ['BY'],
            ['CI'],
            ['CD'],
            ['CG'],
            ['IQ'],
            ['LR'],
            ['LB'],
            ['KP'],
            ['SL'],
            ['SY'],
            ['ZW'],
            ['AL'],
            ['BA'],
            ['MK'],
            ['ME'],
            ['RS']
        ];
    }

    /**
     * Init country collection mock
     * @param array $countries
     */
    protected function initCountryCollectionMock(array $countries): void
    {
        $this->countryCollectionMock->expects(static::once())
            ->method('addFieldToFilter')
            ->willReturnSelf();
        $this->countryCollectionMock->expects(static::once())
            ->method('loadData')
            ->willReturnSelf();
        $this->countryCollectionMock->expects(static::once())
            ->method('toOptionArray')
            ->willReturn($countries);
    }
}

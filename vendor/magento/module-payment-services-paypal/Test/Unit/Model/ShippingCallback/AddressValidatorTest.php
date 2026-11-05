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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\ShippingCallback;

use ArrayIterator;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region\Collection as RegionCollection;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\AddressValidator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AddressValidatorTest extends TestCase
{
    /**
     * @var AddressInterfaceFactory|MockObject
     */
    private $addressFactory;

    /**
     * @var RegionFactory|MockObject
     */
    private $regionFactory;

    /**
     * @var AllowedCountries|MockObject
     */
    private $allowedCountries;

    /**
     * @var RegionCollectionFactory|MockObject
     */
    private $regionCollectionFactory;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var AddressValidator
     */
    private AddressValidator $addressValidator;

    protected function setUp(): void
    {
        $this->addressFactory = $this->createMock(AddressInterfaceFactory::class);
        $this->regionFactory = $this->createMock(RegionFactory::class);
        $this->allowedCountries = $this->createMock(AllowedCountries::class);
        $this->regionCollectionFactory = $this->createMock(RegionCollectionFactory::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);

        $this->addressValidator = new AddressValidator(
            $this->addressFactory,
            $this->regionFactory,
            $this->allowedCountries,
            $this->regionCollectionFactory,
            $this->cartRepository,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testValidateAndSetAddressSavesValidatedAddress(): void
    {
        $shippingAddress = [
            'country_code' => 'US',
            'admin_area_1' => 'CA',
            'admin_area_2' => 'San Jose',
            'postal_code' => '95131',
            'telephone' => '5551234567'
        ];
        $address = $this->createAddress();
        $quote = $this->createMock(Quote::class);

        $this->allowedCountries->method('getAllowedCountries')
            ->willReturn(['US', 'GB']);
        $this->prepareCountriesWithPreDefinedRegions(['US']);
        $this->prepareRegion(12);

        $this->addressFactory->expects($this->once())
            ->method('create')
            ->willReturn($address);
        $quote->expects($this->once())
            ->method('setShippingAddress')
            ->with($this->identicalTo($address))
            ->willReturnSelf();
        $quote->expects($this->once())
            ->method('setBillingAddress')
            ->with($this->identicalTo($address))
            ->willReturnSelf();
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getBillingAddress')->willReturn($address);
        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($quote));

        $this->addressValidator->validateAndSetAddress($quote, $shippingAddress);

        $this->assertSame('firstname', $address->getFirstname());
        $this->assertSame('lastname', $address->getLastname());
        $this->assertSame('5551234567', $address->getTelephone());
        $this->assertSame('San Jose', $address->getCity());
        $this->assertSame('US', $address->getCountryId());
        $this->assertSame('95131', $address->getPostcode());
        $this->assertSame(12, $address->getRegionId());
        $this->assertTrue($address->getShouldIgnoreValidation());
        $this->assertNull($address->getCustomerAddressId());
    }

    public function testValidateAndSetAddressDetachesSavedCustomerAddresses(): void
    {
        $shippingAddress = [
            'country_code' => 'US',
            'admin_area_1' => 'CA',
            'admin_area_2' => 'San Jose',
            'postal_code' => '95131'
        ];
        $address = $this->createAddress();
        $quoteShippingAddress = $this->createAddress();
        $quoteShippingAddress->setCustomerAddressId(10);
        $quoteBillingAddress = $this->createAddress();
        $quoteBillingAddress->setCustomerAddressId(20);
        $quote = $this->createMock(Quote::class);

        $this->allowedCountries->method('getAllowedCountries')->willReturn(['US']);
        $this->prepareCountriesWithPreDefinedRegions(['US']);
        $this->prepareRegion(12);
        $this->addressFactory->method('create')->willReturn($address);
        $quote->method('setShippingAddress')->willReturnSelf();
        $quote->method('setBillingAddress')->willReturnSelf();
        $quote->method('getShippingAddress')->willReturn($quoteShippingAddress);
        $quote->method('getBillingAddress')->willReturn($quoteBillingAddress);
        $this->cartRepository->expects($this->once())->method('save')->with($quote);

        $this->addressValidator->validateAndSetAddress($quote, $shippingAddress);

        $this->assertNull($quoteShippingAddress->getCustomerAddressId());
        $this->assertNull($quoteBillingAddress->getCustomerAddressId());
    }

    public function testValidateAndSetAddressRejectsDisallowedCountry(): void
    {
        $this->allowedCountries->expects($this->once())
            ->method('getAllowedCountries')
            ->willReturn(['US']);
        $this->regionCollectionFactory->expects($this->never())
            ->method('create');
        $this->cartRepository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('COUNTRY_ERROR');

        $this->addressValidator->validateAndSetAddress(
            $this->createMock(Quote::class),
            [
                'country_code' => 'FR',
                'admin_area_1' => 'IDF',
                'postal_code' => '75001'
            ]
        );
    }

    public function testValidateAndSetAddressRejectsInvalidPostalCode(): void
    {
        $this->allowedCountries->method('getAllowedCountries')
            ->willReturn(['GB']);
        $this->prepareCountriesWithPreDefinedRegions([]);
        $this->cartRepository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('ZIP_ERROR');

        $this->addressValidator->validateAndSetAddress(
            $this->createMock(Quote::class),
            [
                'country_code' => 'GB',
                'admin_area_1' => null,
                'postal_code' => 'SW1A@1AA'
            ]
        );
    }

    /**
     * @param string[] $countryIds
     */
    private function prepareCountriesWithPreDefinedRegions(array $countryIds): void
    {
        $collection = $this->getMockBuilder(RegionCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToSelect', 'addFieldToFilter', 'getSelect', 'getSize', 'getIterator'])
            ->getMock();
        $select = $this->createMock(Select::class);
        $regions = array_map(
            static function (string $countryId): DataObject {
                return new DataObject(['country_id' => $countryId]);
            },
            $countryIds
        );

        $collection->expects($this->once())
            ->method('addFieldToSelect')
            ->with('country_id')
            ->willReturnSelf();
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('country_id', $this->anything())
            ->willReturnSelf();
        $collection->expects($this->once())
            ->method('getSelect')
            ->willReturn($select);
        $select->expects($this->once())
            ->method('distinct');
        $collection->expects($this->once())
            ->method('getSize')
            ->willReturn(count($regions));
        $collection->method('getIterator')
            ->willReturn(new ArrayIterator($regions));

        $this->regionCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);
    }

    private function prepareRegion(int $regionId): void
    {
        $region = $this->getMockBuilder(Region::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadByCode'])
            ->getMock();
        $region->setData('region_id', $regionId);
        $region->expects($this->exactly(2))
            ->method('loadByCode')
            ->with('CA', 'US')
            ->willReturnSelf();

        $this->regionFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturn($region);
    }

    private function createAddress(): Address
    {
        $reflection = new ReflectionClass(Address::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}

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

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Model\ShippingInformation;
use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\ShippingProcessor;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Shipping;
use Magento\Quote\Model\ShippingAssignment;
use Magento\Quote\Model\ShippingAssignmentFactory;
use Magento\Quote\Model\ShippingFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShippingProcessorTest extends TestCase
{
    /**
     * @var ShippingInformationManagementInterface|MockObject
     */
    private $shippingInformationManagement;

    /**
     * @var ShippingInformationInterfaceFactory|MockObject
     */
    private $shippingInformationFactory;

    /**
     * @var ShipmentEstimationInterface|MockObject
     */
    private $shipmentEstimation;

    /**
     * @var CartExtensionFactory|MockObject
     */
    private $cartExtensionFactory;

    /**
     * @var ShippingAssignmentFactory|MockObject
     */
    private $shippingAssignmentFactory;

    /**
     * @var ShippingFactory|MockObject
     */
    private $shippingFactory;

    /**
     * @var ShippingProcessor
     */
    private ShippingProcessor $shippingProcessor;

    protected function setUp(): void
    {
        $this->shippingInformationManagement = $this->createMock(ShippingInformationManagementInterface::class);
        $this->shippingInformationFactory = $this->createMock(ShippingInformationInterfaceFactory::class);
        $this->shipmentEstimation = $this->createMock(ShipmentEstimationInterface::class);
        $this->cartExtensionFactory = $this->createMock(CartExtensionFactory::class);
        $this->shippingAssignmentFactory = $this->createMock(ShippingAssignmentFactory::class);
        $this->shippingFactory = $this->createMock(ShippingFactory::class);

        $this->shippingProcessor = new ShippingProcessor(
            $this->shippingInformationManagement,
            $this->shippingInformationFactory,
            $this->shipmentEstimation,
            $this->cartExtensionFactory,
            $this->shippingAssignmentFactory,
            $this->shippingFactory
        );
    }

    public function testProcessShippingOptionsPreservesMethodCodeWithUnderscores(): void
    {
        $shippingAddress = $this->createShippingAddress();
        $billingAddress = $this->createAddress();
        $shippingInformation = $this->createShippingInformation();
        $quote = $this->createQuote($shippingAddress, $billingAddress, 123);

        $shippingAddress->expects($this->once())
            ->method('collectShippingRates')
            ->willReturnSelf();
        $this->expectShippingInformationSave(
            $shippingInformation,
            $shippingAddress,
            $billingAddress,
            123,
            'fedex',
            'FEDEX_GROUND'
        );

        $this->shippingProcessor->processShippingOptions(
            $quote,
            [
                'id' => 'fedex|FEDEX_GROUND',
                'amount' => ['value' => 12.34]
            ]
        );

        $this->assertSame('fedex_FEDEX_GROUND', $shippingAddress->getShippingMethod());
        $this->assertSame(12.34, $shippingAddress->getShippingAmount());
    }

    public function testProcessShippingOptionsSupportsCarrierAndMethodCodesWithUnderscores(): void
    {
        $shippingAddress = $this->createShippingAddress();
        $billingAddress = $this->createAddress();
        $shippingInformation = $this->createShippingInformation();
        $quote = $this->createQuote($shippingAddress, $billingAddress, 123);

        $shippingAddress->expects($this->once())
            ->method('collectShippingRates')
            ->willReturnSelf();
        $this->expectShippingInformationSave(
            $shippingInformation,
            $shippingAddress,
            $billingAddress,
            123,
            'custom_carrier',
            'METHOD_GROUND'
        );

        $this->shippingProcessor->processShippingOptions(
            $quote,
            [
                'id' => 'custom_carrier|METHOD_GROUND',
                'amount' => ['value' => 12.34]
            ]
        );

        $this->assertSame('custom_carrier_METHOD_GROUND', $shippingAddress->getShippingMethod());
    }

    public function testGetShippingMethodsFormatsSelectedMethodsAndFiltersInStorePickup(): void
    {
        $shippingAddress = $this->createAddress();
        $shippingAddress->setShippingMethod('fedex_FEDEX_GROUND');
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes', 'getShippingAddress', 'getId'])
            ->getMock();
        $cartExtension = $this->createCartExtension([]);

        $quote->setData('base_currency_code', 'USD');
        $quote->method('getExtensionAttributes')
            ->willReturn($cartExtension);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('getId')
            ->willReturn(321);
        $this->shipmentEstimation->expects($this->once())
            ->method('estimateByExtendedAddress')
            ->with(321, $this->identicalTo($shippingAddress))
            ->willReturn([
                $this->createShippingMethod('fedex', 'FEDEX_GROUND', 'FedEx', 'Ground', 10.126),
                $this->createShippingMethod('instore', 'pickup', 'In-Store Pickup', null, 0.00),
                $this->createShippingMethod('flatrate', 'flatrate', 'Flat Rate', null, 5.00)
            ]);

        $this->assertSame(
            [
                [
                    'id' => 'fedex|FEDEX_GROUND',
                    'label' => 'FedEx - Ground',
                    'type' => 'SHIPPING',
                    'selected' => true,
                    'amount' => [
                        'value' => 10.13,
                        'currency_code' => 'USD'
                    ]
                ],
                [
                    'id' => 'flatrate|flatrate',
                    'label' => 'Flat Rate',
                    'type' => 'SHIPPING',
                    'selected' => false,
                    'amount' => [
                        'value' => 5.00,
                        'currency_code' => 'USD'
                    ]
                ]
            ],
            $this->shippingProcessor->getShippingMethods($quote)
        );
    }

    public function testGetShippingMethodsThrowsWhenNoMethodsRemainAfterFiltering(): void
    {
        $shippingAddress = $this->createAddress();
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes', 'getShippingAddress', 'getId'])
            ->getMock();
        $cartExtension = $this->createCartExtension([]);

        $quote->setData('base_currency_code', 'USD');
        $quote->method('getExtensionAttributes')
            ->willReturn($cartExtension);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('getId')
            ->willReturn(321);
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([
                $this->createShippingMethod('instore', 'pickup', 'In-Store Pickup', null, 0.00)
            ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('METHOD_UNAVAILABLE');

        $this->shippingProcessor->getShippingMethods($quote);
    }

    public function testSetDefaultShippingMethodPreparesShippingAssignmentWhenNoneSelected(): void
    {
        $shippingAddress = $this->createShippingAddress();
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getExtensionAttributes', 'setExtensionAttributes', 'save'])
            ->getMock();
        $cartExtension = $this->createCartExtension();
        $shippingAssignment = $this->createShippingAssignment();
        $shipping = $this->createShipping();

        $shippingAddress->expects($this->once())
            ->method('collectShippingRates')
            ->willReturnSelf();
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->expects($this->once())
            ->method('getExtensionAttributes')
            ->willReturn(null);
        $quote->expects($this->once())
            ->method('setExtensionAttributes')
            ->with($this->identicalTo($cartExtension))
            ->willReturnSelf();
        $quote->expects($this->once())
            ->method('save')
            ->willReturnSelf();
        $this->cartExtensionFactory->expects($this->once())
            ->method('create')
            ->willReturn($cartExtension);
        $this->shippingAssignmentFactory->expects($this->once())
            ->method('create')
            ->willReturn($shippingAssignment);
        $this->shippingFactory->expects($this->once())
            ->method('create')
            ->willReturn($shipping);

        $result = $this->shippingProcessor->setDefaultShippingMethod(
            $quote,
            [
                [
                    'id' => 'flatrate|flatrate',
                    'selected' => false
                ]
            ]
        );

        $this->assertTrue($result[0]['selected']);
        $this->assertSame('flatrate_flatrate', $shippingAddress->getShippingMethod());
        $this->assertSame([$shippingAssignment], $cartExtension->getShippingAssignments());
        $this->assertSame($shipping, $shippingAssignment->getShipping());
        $this->assertSame($shippingAddress, $shipping->getAddress());
        $this->assertSame('flatrate_flatrate', $shipping->getMethod());
    }

    private function createQuote(Address $shippingAddress, Address $billingAddress, int $quoteId): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'getId'])
            ->getMock();
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('getBillingAddress')
            ->willReturn($billingAddress);
        $quote->method('getId')
            ->willReturn($quoteId);

        return $quote;
    }

    private function expectShippingInformationSave(
        ShippingInformationInterface $shippingInformation,
        Address $shippingAddress,
        Address $billingAddress,
        int $quoteId,
        string $carrierCode,
        string $methodCode
    ): void {
        $this->shippingInformationFactory->expects($this->once())
            ->method('create')
            ->willReturn($shippingInformation);
        $this->shippingInformationManagement->expects($this->once())
            ->method('saveAddressInformation')
            ->with(
                $quoteId,
                $this->callback(
                    function (ShippingInformationInterface $information) use (
                        $shippingAddress,
                        $billingAddress,
                        $carrierCode,
                        $methodCode
                    ): bool {
                        $this->assertSame($shippingAddress, $information->getShippingAddress());
                        $this->assertSame($billingAddress, $information->getBillingAddress());
                        $this->assertSame($carrierCode, $information->getShippingCarrierCode());
                        $this->assertSame($methodCode, $information->getShippingMethodCode());
                        return true;
                    }
                )
            );
    }

    private function createShippingMethod(
        string $carrierCode,
        string $methodCode,
        string $carrierTitle,
        ?string $methodTitle,
        float $baseAmount
    ): ShippingMethodInterface {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCarrierCode')
            ->willReturn($carrierCode);
        $method->method('getMethodCode')
            ->willReturn($methodCode);
        $method->method('getCarrierTitle')
            ->willReturn($carrierTitle);
        $method->method('getMethodTitle')
            ->willReturn($methodTitle);
        $method->method('getBaseAmount')
            ->willReturn($baseAmount);

        return $method;
    }

    private function createShippingAddress(): Address
    {
        return $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectShippingRates'])
            ->getMock();
    }

    private function createAddress(): Address
    {
        $reflection = new ReflectionClass(Address::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    private function createShippingInformation(): ShippingInformationInterface
    {
        $reflection = new ReflectionClass(ShippingInformation::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    private function createCartExtension(array $shippingAssignments = []): CartExtensionWithShippingAssignments
    {
        $cartExtension = new CartExtensionWithShippingAssignments();
        $cartExtension->setShippingAssignments($shippingAssignments);

        return $cartExtension;
    }

    private function createShippingAssignment(): ShippingAssignment
    {
        $reflection = new ReflectionClass(ShippingAssignment::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    private function createShipping(): Shipping
    {
        $reflection = new ReflectionClass(Shipping::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}

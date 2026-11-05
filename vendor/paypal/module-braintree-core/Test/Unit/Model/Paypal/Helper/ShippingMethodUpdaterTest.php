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
namespace PayPal\Braintree\Test\Unit\Model\Paypal\Helper;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use PayPal\Braintree\Model\Paypal\Helper\ShippingMethodUpdater;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @see ShippingMethodUpdater
 */
class ShippingMethodUpdaterTest extends \PHPUnit\Framework\TestCase
{
    use MockCreationTrait;

    private const TEST_SHIPPING_METHOD = 'test-shipping-method';
    private const TEST_EMAIL = 'test@test.loc';

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private CartRepositoryInterface|MockObject $quoteRepositoryMock;

    /**
     * @var Address|MockObject
     */
    private Address|MockObject $shippingAddressMock;

    /**
     * @var Address|MockObject
     */
    private Address|MockObject $billingAddressMock;

    /**
     * @var ShippingMethodUpdater
     */
    private ShippingMethodUpdater $shippingMethodUpdater;

    protected function setUp(): void
    {
        $this->quoteRepositoryMock = $this->getMockBuilder(CartRepositoryInterface::class)
            ->getMock();

        $this->shippingAddressMock = $this->createMock(Address::class);

        $this->shippingMethodUpdater = new ShippingMethodUpdater(
            $this->quoteRepositoryMock
        );
    }

    /**
     */
    public function testExecuteException()
    {
        $this->markTestSkipped('Skip this test');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "shippingMethod" field does not exist.');

        $quoteMock = $this->getQuoteMock();

        $this->shippingMethodUpdater->execute('', $quoteMock);
    }

    public function testExecute()
    {
        $this->markTestSkipped('Skip this test');
        $quoteMock = $this->getQuoteMock();

        $quoteMock->expects(self::exactly(2))
            ->method('getIsVirtual')
            ->willReturn(false);

        $quoteMock->expects(self::exactly(2))
            ->method('getShippingAddress')
            ->willReturn($this->shippingAddressMock);

        $this->shippingAddressMock->expects(self::once())
            ->method('getShippingMethod')
            ->willReturn(self::TEST_SHIPPING_METHOD . '-bad');

        $this->disabledQuoteAddressValidationStep($quoteMock);

        $this->shippingAddressMock->expects(self::once())
            ->method('setShippingMethod')
            ->willReturn(self::TEST_SHIPPING_METHOD);
        $this->shippingAddressMock->expects(self::once())
            ->method('setCollectShippingRates')
            ->willReturn(true);

        $quoteMock->expects(self::once())
            ->method('collectTotals');

        $this->quoteRepositoryMock->expects(self::once())
            ->method('save')
            ->with($quoteMock);

        $this->shippingMethodUpdater->execute(self::TEST_SHIPPING_METHOD, $quoteMock);
    }

    /**
     * @param MockObject $quoteMock
     */
    private function disabledQuoteAddressValidationStep(MockObject $quoteMock): void
    {
        $billingAddressMock = $this->getBillingAddressMock($quoteMock);

        $billingAddressMock->expects(self::once())
            ->method('setShouldIgnoreValidation')
            ->with(true)
            ->willReturnSelf();

        $this->shippingAddressMock->expects(self::once())
            ->method('setShouldIgnoreValidation')
            ->with(true)
            ->willReturnSelf();

        $billingAddressMock->method('getEmail')
            ->willReturn(self::TEST_EMAIL);

        $billingAddressMock->expects(self::never())
            ->method('setSameAsBilling');
    }

    /**
     * @param MockObject $quoteMock
     * @return Address|MockObject
     */
    private function getBillingAddressMock(MockObject $quoteMock): MockObject|Address
    {
        if (!isset($this->billingAddressMock)) {
            $this->billingAddressMock = $this->getMockBuilder(Address::class)
                ->onlyMethods(['setShouldIgnoreValidation', 'getEmail', 'setSameAsBilling'])
                ->disableOriginalConstructor()
                ->getMock();
        }

        $quoteMock->expects(self::any())
            ->method('getBillingAddress')
            ->willReturn($this->billingAddressMock);

        return $this->billingAddressMock;
    }

    /**
     * @return Quote|MockObject
     */
    private function getQuoteMock(): MockObject|Quote
    {
        return $this->getMockBuilder(Quote::class)
            ->onlyMethods([
                'collectTotals',
                'getBillingAddress',
                'getShippingAddress',
                'getIsVirtual'
            ])->disableOriginalConstructor()
            ->getMock();
    }
}

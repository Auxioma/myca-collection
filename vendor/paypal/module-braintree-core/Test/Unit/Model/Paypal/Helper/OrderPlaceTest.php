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

use Magento\Checkout\Api\AgreementsValidatorInterface;
use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PayPal\Braintree\Model\Paypal\Helper\OrderPlace;
use PayPal\Braintree\Model\Paypal\OrderCancellationService;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @see \PayPal\Braintree\Model\Paypal\Helper\OrderPlace
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderPlaceTest extends \PHPUnit\Framework\TestCase
{
    private const TEST_EMAIL = 'test@test.loc';

    /**
     * @var CartManagementInterface|MockObject
     */
    private CartManagementInterface|MockObject $cartManagementMock;

    /**
     * @var AgreementsValidatorInterface|MockObject
     */
    private AgreementsValidatorInterface|MockObject $agreementsValidatorMock;

    /**
     * @var Session|MockObject
     */
    private Session|MockObject $customerSessionMock;

    /**
     * @var Data|MockObject
     */
    private Data|MockObject $checkoutHelperMock;

    /**
     * @var Address|MockObject
     */
    private Address|MockObject $billingAddressMock;

    /**
     * @var OrderPlace
     */
    private OrderPlace $orderPlace;

    /**
     * @var OrderCancellationService|MockObject
     */
    private OrderCancellationService|MockObject $orderCancellationServiceMock;

    protected function setUp(): void
    {
        $this->cartManagementMock = $this->getMockBuilder(CartManagementInterface::class)
            ->getMock();
        $this->agreementsValidatorMock = $this->getMockBuilder(AgreementsValidatorInterface::class)
            ->getMock();
        $this->customerSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutHelperMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderCancellationServiceMock = $this->getMockBuilder(OrderCancellationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderPlace = new OrderPlace(
            $this->cartManagementMock,
            $this->agreementsValidatorMock,
            $this->customerSessionMock,
            $this->checkoutHelperMock,
            $this->orderCancellationServiceMock
        );
    }

    public function testExecuteGuest()
    {
        $this->markTestSkipped('Skip this test');
        $agreement = ['test', 'test'];
        $quoteMock = $this->getQuoteMock();

        $this->agreementsValidatorMock->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $this->getCheckoutMethodStep($quoteMock);
        $this->prepareGuestQuoteStep($quoteMock);
        $this->disabledQuoteAddressValidationStep($quoteMock);

        $quoteMock->expects(self::once())
            ->method('collectTotals');

        $quoteMock->expects(self::once())
            ->method('getId')
            ->willReturn(10);

        $this->cartManagementMock->expects(self::once())
            ->method('placeOrder')
            ->with(10);

        $this->orderPlace->execute($quoteMock, $agreement);
    }

    /**
     * @param MockObject $quoteMock
     */
    private function disabledQuoteAddressValidationStep(MockObject $quoteMock)
    {
        $billingAddressMock = $this->getBillingAddressMock($quoteMock);
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->onlyMethods(['setShouldIgnoreValidation'])
            ->disableOriginalConstructor()
            ->getMock();

        $quoteMock->expects(self::once())
            ->method('getShippingAddress')
            ->willReturn($shippingAddressMock);

        $billingAddressMock->expects(self::once())
            ->method('setShouldIgnoreValidation')
            ->with(true)
            ->willReturnSelf();

        $quoteMock->expects(self::once())
            ->method('getIsVirtual')
            ->willReturn(false);

        $shippingAddressMock->expects(self::once())
            ->method('setShouldIgnoreValidation')
            ->with(true)
            ->willReturnSelf();

        $billingAddressMock->expects(self::any())
            ->method('getEmail')
            ->willReturn(self::TEST_EMAIL);

        $billingAddressMock->expects(self::never())
            ->method('setSameAsBilling');
    }

    /**
     * @param MockObject $quoteMock
     */
    private function getCheckoutMethodStep(MockObject $quoteMock)
    {
        $this->customerSessionMock->expects(self::once())
            ->method('isLoggedIn')
            ->willReturn(false);

        $quoteMock->method('getCheckoutMethod')
            ->willReturnOnConsecutiveCalls(null, Onepage::METHOD_GUEST);

        $this->checkoutHelperMock->expects(self::once())
            ->method('isAllowedGuestCheckout')
            ->with($quoteMock)
            ->willReturn(true);

        $quoteMock->expects(self::once())
            ->method('setCheckoutMethod')
            ->with(Onepage::METHOD_GUEST);
    }

    /**
     * @param MockObject $quoteMock
     */
    private function prepareGuestQuoteStep(MockObject $quoteMock)
    {
        $billingAddressMock = $this->getBillingAddressMock($quoteMock);

        $quoteMock->expects(self::once())
            ->method('setCustomerId')
            ->with(null)
            ->willReturnSelf();

        $billingAddressMock->method('getEmail')
            ->willReturn(self::TEST_EMAIL);

        $quoteMock->expects(self::once())
            ->method('setCustomerEmail')
            ->with(self::TEST_EMAIL)
            ->willReturnSelf();

        $quoteMock->expects(self::once())
            ->method('setCustomerIsGuest')
            ->with(true)
            ->willReturnSelf();

        $quoteMock->expects(self::once())
            ->method('setCustomerGroupId')
            ->with(Group::NOT_LOGGED_IN_ID)
            ->willReturnSelf();
    }

    /**
     * @param MockObject $quoteMock
     * @return Address|MockObject
     */
    private function getBillingAddressMock(MockObject $quoteMock)
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
    private function getQuoteMock()
    {
        return $this->getMockBuilder(Quote::class)
            ->onlyMethods(
                [
                    'setCustomerId',
                    'setCustomerEmail',
                    'setCustomerIsGuest',
                    'setCustomerGroupId',
                    'getCheckoutMethod',
                    'setCheckoutMethod',
                    'collectTotals',
                    'getId',
                    'getBillingAddress',
                    'getShippingAddress',
                    'getIsVirtual'
                ]
            )->disableOriginalConstructor()
            ->getMock();
    }
}

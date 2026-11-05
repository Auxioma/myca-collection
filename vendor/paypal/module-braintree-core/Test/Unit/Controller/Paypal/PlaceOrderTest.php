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
namespace PayPal\Braintree\Test\Unit\Controller\Paypal;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Quote\Model\Quote;
use PayPal\Braintree\Controller\Paypal\PlaceOrder;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Paypal\Helper\OrderPlace;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see PlaceOrder
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlaceOrderTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var OrderPlace|MockObject
     */
    private OrderPlace|MockObject $orderPlaceMock;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $configMock;

    /**
     * @var Session|MockObject
     */
    private Session|MockObject $checkoutSessionMock;

    /**
     * @var RequestInterface|MockObject
     */
    private RequestInterface|MockObject $requestMock;

    /**
     * @var ResultFactory|MockObject
     */
    private ResultFactory|MockObject $resultFactoryMock;

    /**
     * @var ManagerInterface|MockObject
     */
    protected ManagerInterface|MockObject $messageManagerMock;

    /**
     * @var PlaceOrder
     */
    private PlaceOrder $placeOrder;

    protected function setUp(): void
    {
        /** @var Context|MockObject $contextMock */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock = $this->createPartialMockWithReflection(
            RequestInterface::class,
            [
                'getModuleName',
                'setModuleName',
                'getActionName',
                'setActionName',
                'getParam',
                'setParams',
                'getParams',
                'getCookie',
                'isSecure',
                'getPostValue'
            ]
        );
        $this->resultFactoryMock = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderPlaceMock = $this->getMockBuilder(OrderPlace::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);

        $contextMock->expects(self::once())
            ->method('getRequest')
            ->willReturn($this->requestMock);
        $contextMock->expects(self::once())
            ->method('getResultFactory')
            ->willReturn($this->resultFactoryMock);
        $contextMock->expects(self::once())
            ->method('getMessageManager')
            ->willReturn($this->messageManagerMock);

        $this->placeOrder = new PlaceOrder(
            $contextMock,
            $this->configMock,
            $this->checkoutSessionMock,
            $this->orderPlaceMock
        );
    }

    /**
     * @throws NotFoundException
     */
    public function testExecute()
    {
        $agreement = ['test-data'];

        $quoteMock = $this->getQuoteMock();
        $quoteMock->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(1);

        $resultMock = $this->getResultMock();
        $resultMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/onepage/success')
            ->willReturnSelf();

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($resultMock);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('agreement', [])
            ->willReturn($agreement);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->orderPlaceMock->expects(self::once())
            ->method('execute')
            ->with($quoteMock, [0]);

        $this->messageManagerMock->expects(self::never())
            ->method('addExceptionMessage');

        self::assertEquals($this->placeOrder->execute(), $resultMock);
    }

    /**
     * @throws NotFoundException
     */
    public function testExecuteException()
    {
        $agreement = ['test-data'];

        $quote = $this->getQuoteMock();
        $quote->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(0);

        $resultMock = $this->getResultMock();
        $resultMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($resultMock);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('agreement', [])
            ->willReturn($agreement);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quote);

        $this->orderPlaceMock->expects(self::never())
            ->method('execute');

        $this->messageManagerMock->expects(self::once())
            ->method('addExceptionMessage')
            ->with(self::isInstanceOf('Exception'));

        self::assertEquals($this->placeOrder->execute(), $resultMock);
    }

    /**
     * @return ResultInterface|MockObject
     */
    private function getResultMock(): ResultInterface|MockObject
    {
        return $this->createPartialMockWithReflection(
            ResultInterface::class,
            ['setHttpResponseCode', 'setHeader', 'renderResult', 'setPath']
        );
    }

    /**
     * @return Quote|MockObject
     */
    private function getQuoteMock(): Quote|MockObject
    {
        return $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}

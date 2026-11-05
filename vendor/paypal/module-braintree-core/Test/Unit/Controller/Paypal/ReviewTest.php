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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Result\Page;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PayPal\Braintree\Block\Paypal\Checkout\Review as CheckoutReview;
use PayPal\Braintree\Controller\Paypal\Review;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Paypal\Helper\QuoteUpdater;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @see Review
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReviewTest extends \PHPUnit\Framework\TestCase
{
    use MockCreationTrait;

    /**
     * @var QuoteUpdater|MockObject
     */
    private QuoteUpdater|MockObject $quoteUpdaterMock;

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
    private ManagerInterface|MockObject $messageManagerMock;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $jsonMock;

    /**
     * @var Review
     */
    private Review $review;

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
        $this->quoteUpdaterMock = $this->getMockBuilder(QuoteUpdater::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->jsonMock = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contextMock->expects(self::once())
            ->method('getRequest')
            ->willReturn($this->requestMock);
        $contextMock->expects(self::once())
            ->method('getResultFactory')
            ->willReturn($this->resultFactoryMock);
        $contextMock->expects(self::once())
            ->method('getMessageManager')
            ->willReturn($this->messageManagerMock);

        $this->review = new Review(
            $contextMock,
            $this->configMock,
            $this->checkoutSessionMock,
            $this->quoteUpdaterMock,
            $this->jsonMock
        );
    }

    public function testExecute()
    {
        $result = '{"nonce": "test-nonce", "details": {"firstName": "Test", "lastName": "User"}}';
        $decodedResult = ['nonce' => 'test-nonce', 'details' => ['firstName' => 'Test', 'lastName' => 'User']];

        $resultPageMock = $this->getResultPageMock();
        $layoutMock = $this->getLayoutMock();
        $blockMock = $this->getBlockMock();
        $quoteMock = $this->getQuoteMock();
        $childBlockMock = $this->getChildBlockMock();

        $quoteMock->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(1);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('result', '{}')
            ->willReturn($result);

        $this->jsonMock->expects(self::once())
            ->method('unserialize')
            ->with($result)
            ->willReturn($decodedResult);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->quoteUpdaterMock->expects(self::once())
            ->method('execute')
            ->with('test-nonce', ['firstName' => 'Test', 'lastName' => 'User'], $quoteMock);

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_PAGE)
            ->willReturn($resultPageMock);

        $resultPageMock->expects(self::once())
            ->method('getLayout')
            ->willReturn($layoutMock);

        $layoutMock->expects(self::once())
            ->method('getBlock')
            ->with('braintree.paypal.review')
            ->willReturn($blockMock);

        $blockMock->expects(self::once())
            ->method('setQuote')
            ->with($quoteMock);
        $blockMock->expects(self::once())
            ->method('getChildBlock')
            ->with('shipping_method')
            ->willReturn($childBlockMock);

        $childBlockMock->expects(self::once())
            ->method('setData')
            ->with('quote', $quoteMock);

        self::assertEquals($this->review->execute(), $resultPageMock);
    }

    public function testExecuteException()
    {
        $result = '{}';
        $quoteMock = $this->getQuoteMock();
        $resultRedirectMock = $this->getResultRedirectMock();

        $quoteMock->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(0);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('result', '{}')
            ->willReturn($result);

        $this->jsonMock->expects(self::once())
            ->method('unserialize')
            ->with($result)
            ->willReturn([]);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->quoteUpdaterMock->expects(self::never())
            ->method('execute');

        $this->messageManagerMock->expects(self::once())
            ->method('addExceptionMessage')
            ->with(
                self::isInstanceOf(InvalidArgumentException::class),
                'We can\'t initialize checkout.'
            );

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($resultRedirectMock);

        $resultRedirectMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/cart', ['_secure' => true])
            ->willReturnSelf();

        self::assertEquals($this->review->execute(), $resultRedirectMock);
    }

    public function testExecuteExceptionPaymentWithoutNonce()
    {
        $result = '{}';
        $quoteMock = $this->getQuoteMock();
        $resultRedirectMock = $this->getResultRedirectMock();

        $quoteMock->expects(self::once())
            ->method('getItemsCount')
            ->willReturn(1);

        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $quoteMock->expects(self::once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $this->requestMock->expects(self::once())
            ->method('getPostValue')
            ->with('result', '{}')
            ->willReturn($result);

        $this->jsonMock->expects(self::once())
            ->method('unserialize')
            ->with($result)
            ->willReturn([]);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->messageManagerMock->expects(self::once())
            ->method('addExceptionMessage')
            ->with(
                self::isInstanceOf(LocalizedException::class),
                'We can\'t initialize checkout.'
            );

        $this->resultFactoryMock->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($resultRedirectMock);

        $resultRedirectMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/cart', ['_secure' => true])
            ->willReturnSelf();

        self::assertEquals($this->review->execute(), $resultRedirectMock);
    }

    /**
     * @return Redirect|MockObject
     */
    private function getResultRedirectMock(): MockObject|Redirect
    {
        return $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return AbstractBlock|MockObject
     */
    private function getChildBlockMock(): AbstractBlock|MockObject
    {
        return $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return CheckoutReview|MockObject
     */
    private function getBlockMock(): MockObject|CheckoutReview
    {
        return $this->getMockBuilder(CheckoutReview::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Layout|MockObject
     */
    private function getLayoutMock(): Layout|MockObject
    {
        return $this->getMockBuilder(Layout::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Page|MockObject
     */
    private function getResultPageMock(): Page|MockObject
    {
        return $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Quote|MockObject
     */
    private function getQuoteMock(): MockObject|Quote
    {
        return $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}

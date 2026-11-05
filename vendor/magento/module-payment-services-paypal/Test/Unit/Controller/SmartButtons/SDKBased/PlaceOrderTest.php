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

namespace Magento\PaymentServicesPaypal\Test\Unit\Controller\SmartButtons\SDKBased;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesPaypal\Controller\SmartButtons\SDKBased\PlaceOrder;
use Magento\PaymentServicesPaypal\Model\CancellationService;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\QuoteSynchronizer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use TypeError;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlaceOrderTest extends TestCase
{
    /**
     * @var HttpRequest|MockObject
     */
    private HttpRequest|MockObject $request;

    /**
     * @var ResultFactory|MockObject
     */
    private ResultFactory|MockObject $resultFactory;

    /**
     * @var Checkout|MockObject
     */
    private Checkout|MockObject $checkout;

    /**
     * @var UrlInterface|MockObject
     */
    private UrlInterface|MockObject $url;

    /**
     * @var CancellationService|MockObject
     */
    private CancellationService|MockObject $cancellationService;

    /**
     * @var MessageManagerInterface|MockObject
     */
    private MessageManagerInterface|MockObject $messageManager;

    /**
     * @var QuoteSynchronizer|MockObject
     */
    private QuoteSynchronizer|MockObject $quoteSynchronizer;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $jsonResult;

    /**
     * @var PlaceOrder
     */
    private PlaceOrder $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->checkout = $this->createMock(Checkout::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->cancellationService = $this->createMock(CancellationService::class);
        $this->messageManager = $this->createMock(MessageManagerInterface::class);
        $this->quoteSynchronizer = $this->createMock(QuoteSynchronizer::class);
        $this->jsonResult = $this->createMock(Json::class);

        $this->controller = new PlaceOrder(
            $this->request,
            $this->resultFactory,
            $this->checkout,
            $this->url,
            $this->cancellationService,
            $this->messageManager,
            $this->quoteSynchronizer
        );
    }

    public function testConstructorResolvesQuoteSynchronizerWhenOmitted(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('get')
            ->with(QuoteSynchronizer::class)
            ->willReturn($this->quoteSynchronizer);

        $objectManagerInstance = new ReflectionProperty(ObjectManager::class, '_instance');
        $previousObjectManager = $objectManagerInstance->getValue();

        try {
            ObjectManager::setInstance($objectManager);
            $controller = new PlaceOrder(
                $this->request,
                $this->resultFactory,
                $this->checkout,
                $this->url,
                $this->cancellationService,
                $this->messageManager
            );

            $quoteSynchronizerProperty = new ReflectionProperty(PlaceOrder::class, 'quoteSynchronizer');
            self::assertSame($this->quoteSynchronizer, $quoteSynchronizerProperty->getValue($controller));
        } finally {
            $objectManagerInstance->setValue(null, $previousObjectManager);
        }
    }

    /**
     * @param string $paymentSource
     * @param string|null $customerEmail
     * @param bool $shouldSynchronize
     * @return void
     * @dataProvider synchronizationDecisionProvider
     */
    public function testSynchronizesAllowedSourcesRegardlessOfCustomerEmail(
        string $paymentSource,
        ?string $customerEmail,
        bool $shouldSynchronize
    ): void {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('payment_source')
            ->willReturn($paymentSource);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $quote->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);
        $quote->setData('customer_email', $customerEmail);

        $this->configureSuccessfulExecution($quote);

        $synchronized = false;
        $synchronizationExpectation = $this->quoteSynchronizer
            ->expects($shouldSynchronize ? $this->once() : $this->never())
            ->method('synchronize')
            ->with($quote);
        if ($shouldSynchronize) {
            $synchronizationExpectation->willReturnCallback(
                static function () use (&$synchronized): void {
                    $synchronized = true;
                }
            );
        }
        $this->checkout->expects($this->once())->method('placeOrder')
            ->willReturnCallback(static function () use (&$synchronized, $shouldSynchronize): void {
                self::assertSame($shouldSynchronize, $synchronized);
            });
        $this->cancellationService->expects($this->never())->method('execute');
        $this->messageManager->expects($this->never())->method('addExceptionMessage');

        self::assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * @return array<string, array{string, string|null, bool}>
     */
    public static function synchronizationDecisionProvider(): array
    {
        return [
            'PayPal without email' => ['paypal', null, true],
            'Pay Later without email' => ['paylater', null, true],
            'Venmo without email' => ['venmo', null, true],
            'PayPal with email' => ['paypal', 'buyer@example.com', true],
            'Pay Later with email' => ['paylater', 'buyer@example.com', true],
            'Venmo with email' => ['venmo', 'buyer@example.com', true],
            'disallowed source without email' => ['card', null, false],
            'disallowed source with email' => ['card', 'buyer@example.com', false],
        ];
    }

    public function testSynchronizationFailureCancelsBeforeReturningError(): void
    {
        $exception = new LocalizedException(__('Unable to restore the cart. Please try again.'));
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->with('payment_source')->willReturn('paypal');
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getId')->willReturn(7);

        $this->resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($this->jsonResult);
        $this->request->method('getPost')->with('location')->willReturn(null);
        $this->checkout->method('getLocation')->willReturn(Checkout::LOCATION_PRODUCT_PAGE);
        $this->checkout->expects($this->never())->method('unsetQuote');
        $this->checkout->expects($this->once())->method('validateQuote');
        $this->checkout->expects($this->once())->method('getQuote')->willReturn($quote);
        $this->quoteSynchronizer->expects($this->once())->method('synchronize')
            ->with($quote)->willThrowException($exception);
        $this->checkout->expects($this->never())->method('placeOrder');
        $this->cancellationService->expects($this->once())->method('execute')->with(7);
        $this->messageManager->expects($this->once())->method('addExceptionMessage')->with(
            $exception,
            'Unable to restore the cart. Please try again.'
        );
        $this->jsonResult->expects($this->once())->method('setHttpResponseCode')->with(500)->willReturnSelf();
        $this->jsonResult->expects($this->once())->method('setData')->with([
            'error' => 'Unable to restore the cart. Please try again.'
        ])->willReturnSelf();

        self::assertSame($this->jsonResult, $this->controller->execute());
    }

    public function testSynchronizationThrowableCancelsBeforeReturningGenericError(): void
    {
        $exception = new TypeError('Invalid approved address');
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->with('payment_source')->willReturn('paypal');
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getId')->willReturn(8);

        $this->resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($this->jsonResult);
        $this->request->method('getPost')->with('location')->willReturn(null);
        $this->checkout->method('getLocation')->willReturn(Checkout::LOCATION_PRODUCT_PAGE);
        $this->checkout->expects($this->never())->method('unsetQuote');
        $this->checkout->expects($this->once())->method('validateQuote');
        $this->checkout->expects($this->once())->method('getQuote')->willReturn($quote);
        $this->quoteSynchronizer->expects($this->once())->method('synchronize')
            ->with($quote)->willThrowException($exception);
        $this->checkout->expects($this->never())->method('placeOrder');
        $this->cancellationService->expects($this->once())->method('execute')->with(8);
        $this->messageManager->expects($this->once())->method('addExceptionMessage')->with(
            $this->callback(static function (\Exception $actual) use ($exception): bool {
                return $actual instanceof RuntimeException && $actual->getPrevious() === $exception;
            }),
            'We can\'t process the order right now. Please try again later.'
        );
        $this->jsonResult->expects($this->once())->method('setHttpResponseCode')->with(500)->willReturnSelf();
        $this->jsonResult->expects($this->once())->method('setData')->with([
            'error' => 'We can\'t process the order right now. Please try again later.'
        ])->willReturnSelf();

        self::assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * @param Quote $quote
     * @return void
     */
    private function configureSuccessfulExecution(Quote $quote): void
    {
        $successPageUri = 'checkout/onepage/success';
        $redirectUrl = 'https://example.com/checkout/onepage/success';

        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->jsonResult);
        $this->request->expects($this->once())
            ->method('getPost')
            ->with('location')
            ->willReturn(null);
        $this->checkout->expects($this->once())
            ->method('getLocation')
            ->willReturn(Checkout::LOCATION_PRODUCT_PAGE);
        $this->checkout->expects($this->never())->method('unsetQuote');
        $this->checkout->expects($this->once())->method('validateQuote');
        $this->checkout->expects($this->once())
            ->method('getQuote')
            ->willReturn($quote);
        $this->checkout->expects($this->once())
            ->method('getSuccessPageUri')
            ->willReturn($successPageUri);
        $this->url->expects($this->once())
            ->method('getUrl')
            ->with($successPageUri)
            ->willReturn($redirectUrl);
        $this->jsonResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(200)
            ->willReturnSelf();
        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(['redirectUrl' => $redirectUrl])
            ->willReturnSelf();
    }
}

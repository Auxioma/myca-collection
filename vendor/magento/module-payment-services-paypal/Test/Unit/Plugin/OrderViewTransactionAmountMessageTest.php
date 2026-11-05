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

namespace Magento\PaymentServicesPaypal\Test\Unit\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\PaymentServicesPaypal\Gateway\Response\TransactionAmountHandler;
use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\PaymentServicesPaypal\Plugin\OrderViewTransactionAmountMessage;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order\View;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for OrderViewTransactionAmountMessage
 */
class OrderViewTransactionAmountMessageTest extends TestCase
{
    private const ORDER_ID = '42';
    private const TXN_AMOUNT = '100.00';
    private const TXN_CURRENCY = 'USD';

    /**
     * @var OrderViewTransactionAmountMessage
     */
    private OrderViewTransactionAmountMessage $plugin;

    /**
     * @var ManagerInterface|MockObject
     */
    private ManagerInterface|MockObject $messageManager;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private OrderRepositoryInterface|MockObject $orderRepository;

    /**
     * @var View|MockObject
     */
    private View|MockObject $viewController;

    /**
     * @var RequestInterface|MockObject
     */
    private RequestInterface|MockObject $request;

    /**
     * @var OrderInterface|MockObject
     */
    private OrderInterface|MockObject $order;

    /**
     * @var OrderPaymentInterface|MockObject
     */
    private OrderPaymentInterface|MockObject $payment;

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->viewController = $this->createMock(View::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(OrderPaymentInterface::class);

        $this->plugin = new OrderViewTransactionAmountMessage(
            $this->messageManager,
            $this->orderRepository
        );
    }

    /**
     * Test that execution is skipped when order_id is missing from the request
     */
    public function testBeforeExecuteSkipsWhenOrderIdIsNull(): void
    {
        // Having: no order_id param in the request
        $this->havingOrderIdParam(null);
        $this->havingOrderIsNeverLoaded();
        $this->havingNoWarningIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    /**
     * Test that execution is skipped when the order cannot be found
     */
    public function testBeforeExecuteSkipsWhenOrderNotFound(): void
    {
        // Having: a valid order_id but the order does not exist
        $this->havingOrderIdParam(self::ORDER_ID);
        $this->havingOrderRepositoryThrows(new NoSuchEntityException());
        $this->havingNoWarningIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    /**
     * Test that execution is skipped when the payment method is not a Payment Services method
     */
    public function testBeforeExecuteSkipsWhenPaymentMethodIsNotSupported(): void
    {
        // Having: an order paid with a non-Payment-Services method
        $this->havingOrderIdParam(self::ORDER_ID);
        $this->havingOrderWithPaymentMethod('checkmo');
        $this->havingNoWarningIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    /**
     * Test that execution is skipped when the transaction amount is not stored on the payment
     */
    public function testBeforeExecuteSkipsWhenTxnAmountIsNull(): void
    {
        // Having: a supported payment method but no transaction amount in additional info
        $this->havingOrderIdParam(self::ORDER_ID);
        $this->havingOrderWithPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->havingPaymentAdditionalInfo(null, null);
        $this->havingNoWarningIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    /**
     * Test that execution is skipped when the transaction currency is not stored on the payment
     */
    public function testBeforeExecuteSkipsWhenTxnCurrencyIsNull(): void
    {
        // Having: a supported payment method with an amount but no currency
        $this->havingOrderIdParam(self::ORDER_ID);
        $this->havingOrderWithPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->havingPaymentAdditionalInfo(self::TXN_AMOUNT, null);
        $this->havingNoWarningIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    /**
     * Test that execution is skipped when txn currency and order base currency differ
     */
    public function testBeforeExecuteSkipsWhenCurrenciesAreDifferent(): void
    {
        // Having: a txn in EUR but an order settled in USD — amounts cannot be compared
        $this->havingOrderIdParam(self::ORDER_ID);
        $this->havingOrderWithPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->havingPaymentAdditionalInfo(self::TXN_AMOUNT, 'EUR');
        $this->havingOrderAmounts(self::TXN_AMOUNT, 'USD');
        $this->havingNoWarningIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    /**
     * Test that no warning is added when order amount matches transaction amount
     */
    public function testBeforeExecuteDoesNotAddWarningWhenAmountsMatch(): void
    {
        // Having: matching order grand total and transaction amount in the same currency
        $this->havingOrderIdParam(self::ORDER_ID);
        $this->havingOrderWithPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->havingPaymentAdditionalInfo(self::TXN_AMOUNT, self::TXN_CURRENCY);
        $this->havingOrderAmounts(self::TXN_AMOUNT, self::TXN_CURRENCY);
        $this->havingNoWarningIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    /**
     * Test that a warning is shown when order grand total differs from transaction amount
     */
    public function testBeforeExecuteAddsWarningWhenOrderAmountDiffersFromTxnAmount(): void
    {
        // Having: an order grand total that does not match the authorized PayPal transaction amount
        $this->havingOrderIdParam(self::ORDER_ID);
        $this->havingOrderWithPaymentMethod(HostedFieldsConfigProvider::CODE);
        $this->havingPaymentAdditionalInfo('90.00', self::TXN_CURRENCY);
        $this->havingOrderAmounts(self::TXN_AMOUNT, self::TXN_CURRENCY);
        $this->havingWarningMessageIsExpected();

        // When: beforeExecute is called
        $this->whenBeforeExecuteIsCalled();

        // Then: verified by mock expectations set in having section
        $this->thenExpectationsAreVerified();
    }

    // -------------------------------------------------------------------------
    // Having (setup) helpers
    // -------------------------------------------------------------------------

    /**
     * Setup: the request returns the given order_id param
     *
     * @param string|null $orderId
     * @return void
     */
    private function havingOrderIdParam(?string $orderId): void
    {
        $this->viewController->method('getRequest')->willReturn($this->request);
        $this->request->method('getParam')->with('order_id')->willReturn($orderId);
    }

    /**
     * Setup: the order repository is never expected to be called
     *
     * @return void
     */
    private function havingOrderIsNeverLoaded(): void
    {
        $this->orderRepository->expects($this->never())->method('get');
    }

    /**
     * Setup: the order repository throws NoSuchEntityException
     *
     * @param NoSuchEntityException $exception
     * @return void
     */
    private function havingOrderRepositoryThrows(NoSuchEntityException $exception): void
    {
        $this->orderRepository->method('get')->willThrowException($exception);
    }

    /**
     * Setup: the order is found and its payment uses the given method code
     *
     * @param string $methodCode
     * @return void
     */
    private function havingOrderWithPaymentMethod(string $methodCode): void
    {
        $this->orderRepository->method('get')->with(self::ORDER_ID)->willReturn($this->order);
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->payment->method('getMethod')->willReturn($methodCode);
    }

    /**
     * Setup: the payment additional information contains the given txn amount and currency
     *
     * @param string|null $txnAmount
     * @param string|null $txnCurrency
     * @return void
     */
    private function havingPaymentAdditionalInfo(?string $txnAmount, ?string $txnCurrency): void
    {
        $this->payment->method('getAdditionalInformation')
            ->willReturnCallback(function (string $key) use ($txnAmount, $txnCurrency): ?string {
                return match ($key) {
                    TransactionAmountHandler::PP_ORDER_AMOUNT_KEY => $txnAmount,
                    TransactionAmountHandler::PP_ORDER_CURRENCY_KEY => $txnCurrency,
                    default => null,
                };
            });
    }

    /**
     * Setup: the order has the given base grand total and base currency
     *
     * @param string $grandTotal
     * @param string $currencyCode
     * @return void
     */
    private function havingOrderAmounts(string $grandTotal, string $currencyCode): void
    {
        $this->order->method('getBaseGrandTotal')->willReturn($grandTotal);
        $this->order->method('getBaseCurrencyCode')->willReturn($currencyCode);
    }

    /**
     * Setup: no warning message should be added (expectation set before action)
     *
     * @return void
     */
    private function havingNoWarningIsExpected(): void
    {
        $this->messageManager->expects($this->never())->method('addWarningMessage');
    }

    /**
     * Setup: exactly one warning message should be added (expectation set before action)
     *
     * @return void
     */
    private function havingWarningMessageIsExpected(): void
    {
        $this->messageManager->expects($this->once())->method('addWarningMessage');
    }

    // -------------------------------------------------------------------------
    // When (action) helpers
    // -------------------------------------------------------------------------

    /**
     * Action: call beforeExecute on the plugin
     *
     * @return void
     */
    private function whenBeforeExecuteIsCalled(): void
    {
        $this->plugin->beforeExecute($this->viewController);
    }

    // -------------------------------------------------------------------------
    // Then (assertion) helpers
    // -------------------------------------------------------------------------

    /**
     * Assert: all mock expectations set in the having section are verified by PHPUnit at teardown
     *
     * @return void
     */
    private function thenExpectationsAreVerified(): void
    {
        $this->addToAssertionCount(1);
    }
}

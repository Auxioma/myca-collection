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

namespace PayPal\Braintree\Controller\Webhook;

use Braintree\WebhookNotification;
use Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Webhook\Config;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Lpm implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var array Types of webhook events handled by this controller */
    public const ALLOWED_WEBHOOKS = [
        WebhookNotification::CHECK,
        WebhookNotification::LOCAL_PAYMENT_FUNDED,
        WebhookNotification::LOCAL_PAYMENT_EXPIRED,
    ];

    /**
     * @param ResultFactory $resultFactory
     * @param Config $moduleConfig
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param InvoiceService $invoiceService
     * @param OrderManagementInterface $orderManagement
     * @param BraintreeAdapter $braintreeAdapter
     * @param InvoiceSender $invoiceSender
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly Config $moduleConfig,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly InvoiceService $invoiceService,
        private readonly OrderManagementInterface $orderManagement,
        private readonly BraintreeAdapter $braintreeAdapter,
        private readonly InvoiceSender $invoiceSender
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(): ?ResultInterface
    {
        if ($this->moduleConfig->isEnabled()) {
            $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);

            $params = $this->request->getParams();
            try {
                if (!isset($params['bt_signature']) || !isset($params['bt_payload'])) {
                    throw new LocalizedException(__("Request payload does not contain correct params"));
                }

                // Decode webhook and validate request signature
                $webhookNotification = WebhookNotification::parse(
                    $params['bt_signature'],
                    $params['bt_payload']
                );

                if (!in_array($webhookNotification->kind, self::ALLOWED_WEBHOOKS)) {
                    throw new LocalizedException(__("Webhook kind of incorrect type"));
                }

                switch ($webhookNotification->kind) {
                    case WebhookNotification::CHECK:
                        $response->setHttpResponseCode(200);
                        break;

                    case WebhookNotification::LOCAL_PAYMENT_FUNDED:
                        $this->paymentFunded($webhookNotification);
                        $response->setHttpResponseCode(200);
                        break;

                    case WebhookNotification::LOCAL_PAYMENT_EXPIRED:
                        $this->paymentExpired($webhookNotification);
                        $response->setHttpResponseCode(200);
                        break;

                    default:
                        $response->setHttpResponseCode(400);
                }
                return $response;

            } catch (Exception $exception) {
                $this->logger->error(
                    "Braintree LPM webhook: An error occurred during processing",
                    [
                        'exception_message' => $exception->getMessage(),
                    ]
                );

                $response->setHttpResponseCode(400);
                return $response;
            }
        }

        return null;
    }

    /**
     * Once an order has been placed we receive a webhook to confirm the payment
     * - Only process LPM PUI, disregard others,
     * - Verify payment is for correct amount,
     * - Save Braintree transaction_id to the order,
     * - Generate invoice,
     *
     * @param WebhookNotification $webhookNotification
     * @return void
     * @throws LocalizedException
     */
    private function paymentFunded(WebhookNotification $webhookNotification): void
    {
        $paymentId = $webhookNotification->localPaymentFunded->paymentId;
        $transactionId = $webhookNotification->localPaymentFunded->transaction['id'];
        $paidAmount = $webhookNotification->localPaymentFunded->transaction['amount'];
        $paidCurrency = $webhookNotification->localPaymentFunded->transaction['currencyIsoCode'];
        $order = $this->findOrderByPaymentId($paymentId);
        $payment = $order->getPayment();

        // Only need to process PUI payments
        if (!(
            $payment->getMethod() === 'braintree_local_payment'
            && !empty($payment->getAdditionalInformation('paymentId'))
            && $payment->getAdditionalInformation('fundingSource') == 'pay_upon_invoice'
        )) {
            return;
        }

        // Verify the payment is for the correct amount, mark order as payment review on mismatch
        if (!(
            $order->getBaseCurrencyCode() === $paidCurrency &&
            number_format((float) $order->getBaseGrandTotal(), 2) === $paidAmount
        )) {
            $order->addCommentToStatusHistory(
                sprintf(
                    'Amount and currency mismatch on incoming webhook: Received %s %s, expected %s %s',
                    $paidCurrency,
                    $paidAmount,
                    number_format($order->getBaseGrandTotal(), 2),
                    $order->getBaseCurrencyCode()
                ),
                Order::STATE_PAYMENT_REVIEW
            );
        }

        // Update payment information
        $payment->setLastTransId($transactionId);
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionPending(false);
        $payment->setIsTransactionClosed(true);

        // Prepare invoice, update order status and send invoice email to the customer
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);

            // Set as NOT_CAPTURE because PayPal has already captured the payment
            $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
            $invoice->register();
            $invoice->setTransactionId($transactionId);

            $invoice->pay();

            $order->addRelatedObject($invoice);

            // Update order state & status
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus(Order::STATE_PROCESSING);

            $order->addCommentToStatusHistory(
                __('Payment captured by Braintree. Txn ID: %1', $transactionId)
            );

            $this->orderRepository->save($order);

            // Send invoice email to customer and mark as sent
            try {
                $this->invoiceSender->send($invoice);
                $invoice->setEmailSent(true);
                $this->logger->info('Invoice email sent successfully', [
                    'order_id' => $order->getIncrementId(),
                    'invoice_id' => $invoice->getIncrementId(),
                    'transaction_id' => $transactionId,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send invoice email: ' . $e->getMessage(), [
                    'order_id' => $order->getIncrementId(),
                    'transaction_id' => $transactionId,
                    'exception' => $e
                ]);
            }
        }
    }

    /**
     * If a PUI payment fails cancel the order from webhook action
     *
     * @param WebhookNotification $webhookNotification
     * @return void
     */
    private function paymentExpired(WebhookNotification $webhookNotification): void
    {
        $paymentId = $webhookNotification->localPaymentFunded->paymentId;
        $order = $this->findOrderByPaymentId($paymentId);
        $this->orderManagement->cancel($order->getEntityId());
    }

    /**
     * Load order using PUI paymentId
     *
     * We locate via query for performance then load using the standard repository
     *
     * @param string $paymentId
     * @return OrderInterface|null
     */
    public function findOrderByPaymentId(string $paymentId): ?OrderInterface
    {
        try {
            /** @var Collection $collection */
            $collection = $this->orderCollectionFactory->create();

            // Join payment table to search in additional_information
            $collection->getSelect()
                ->join(
                    ['payment' => $collection->getTable('sales_order_payment')],
                    'main_table.entity_id = payment.parent_id',
                    []
                )
                ->where(
                    'payment.additional_information LIKE ?',
                    '%"paymentId":"' . $paymentId . '"%'
                )
                ->limit(1);

            $order = $collection->getFirstItem();

            if ($order && $order->getId()) {
                // Reload order to get full data with proper repository
                return $this->orderRepository->get($order->getId());
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error('Error finding order by Payment ID: ' . $e->getMessage(), [
                'paymentId' => $paymentId,
                'exception' => $e
            ]);

            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}

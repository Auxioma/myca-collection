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
namespace PayPal\Braintree\Plugin;

use Exception;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order\View;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Model\Lpm\Config;
use PayPal\Braintree\Model\Lpm\Ui\ConfigProvider;

class OrderViewPayUponInvoiceMessage
{
    /**
     * @param ManagerInterface $messageManager
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Display warning message for Pay Upon Invoice orders.
     *
     * Shows a warning in the admin order view if the order was placed using
     * Pay Upon Invoice and the invoice has not yet been created via the
     * Braintree webhook.
     *
     * @param View $subject
     * @return void
     * @SuppressWarnings(PHPCS.Magento2.Files.LineLength.MaxExceeded)
     */
    public function beforeExecute(View $subject): void
    {
        $id = $subject->getRequest()->getParam('order_id');
        if ($id === null) {
            return;
        }
        try {
            $order = $this->orderRepository->get($id);
        } catch (Exception $e) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment instanceof Payment) {
            if ($payment->getMethod() !== ConfigProvider::METHOD_CODE) {
                return;
            }

            $fundingSource = $payment->getAdditionalInformation('fundingSource');
            $transactionId = $payment->getLastTransId();

            if ($fundingSource == Config::VALUE_PAY_UPON_INVOICE && $transactionId === null) {
                // phpcs:disable Generic.Files.LineLength.TooLong
                $this->messageManager->addWarningMessage(__('The invoice will be created automatically once Braintree confirms the payment via webhook. This process can take up to 30 minutes. Manual invoice creation is not required and may result in duplicate records.'));
                // phpcs:enable Generic.Files.LineLength.TooLong
            }
        }
    }
}

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

namespace Magento\PaymentServicesPaypal\Plugin;

use Magento\Framework\Message\ManagerInterface;
use Magento\PaymentServicesPaypal\Gateway\Response\TransactionAmountHandler;
use Magento\PaymentServicesPaypal\Model\ApmConfigProvider;
use Magento\PaymentServicesPaypal\Model\ApplePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\FastlaneConfigProvider;
use Magento\PaymentServicesPaypal\Model\GooglePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\PaymentServicesPaypal\Model\SmartButtonsConfigProvider;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order\View;

class OrderViewTransactionAmountMessage
{
    private const PAYMENT_METHODS = [
        HostedFieldsConfigProvider::CODE,
        HostedFieldsConfigProvider::CC_VAULT_CODE,
        SmartButtonsConfigProvider::CODE,
        ApplePayConfigProvider::CODE,
        GooglePayConfigProvider::CODE,
        FastlaneConfigProvider::CODE,
        ApmConfigProvider::CODE,
    ];

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
     * Display a warning when the order grand total does not match the authorized PayPal transaction amount.
     *
     * @param View $subject
     * @return void
     */
    public function beforeExecute(View $subject): void
    {
        $id = $subject->getRequest()->getParam('order_id');
        if ($id === null) {
            return;
        }

        try {
            $order = $this->orderRepository->get($id);
        } catch (\Exception $e) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment === null || !in_array($payment->getMethod(), self::PAYMENT_METHODS, true)) {
            return;
        }

        $orderAmount = $payment->getAdditionalInformation(TransactionAmountHandler::PP_ORDER_AMOUNT_KEY);
        $orderCurrency = $payment->getAdditionalInformation(TransactionAmountHandler::PP_ORDER_CURRENCY_KEY);
        if ($orderAmount === null || $orderCurrency === null) {
            return;
        }

        $baseCurrency = $order->getBaseCurrencyCode();
        if ($orderCurrency !== $baseCurrency) {
            // we can't compare amounts using different currencies
            return;
        }

        if (bccomp((string) $order->getBaseGrandTotal(), (string) $orderAmount, 2) !== 0) {
            $this->messageManager->addWarningMessage(__(
                'Order grand total (%1 %2) does not match the Payment Services authorized amount (%3 %4). '
                . 'Please refund in your PayPal portal and create a new order.',
                $baseCurrency,
                number_format((float) $order->getBaseGrandTotal(), 2),
                $orderCurrency,
                number_format((float) $orderAmount, 2)
            ));
        }
    }
}

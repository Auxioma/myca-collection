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

namespace Magento\PaymentServicesPaypal\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\PaymentServicesPaypal\Gateway\Response\TxnIdHandler;

class TransactionAmountHandler implements HandlerInterface
{
    public const PP_ORDER_AMOUNT_KEY = 'paypal_order_amount';
    public const PP_ORDER_CURRENCY_KEY = 'paypal_order_currency';

    /**
     * Handles transaction amount
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $txnType = $response['mp-transaction']['type'] ?? null;
        if ($txnType !== TxnIdHandler::AUTH_TXN && $txnType !== TxnIdHandler::AUTH_CAPTURE_TXN) {
            return;
        }

        $amount = $response['mp-transaction']['amount'] ?? null;
        if ($amount === null) {
            return;
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];
        $payment = $paymentDO->getPayment();

        if (isset($amount['value'])) {
            $payment->setAdditionalInformation(self::PP_ORDER_AMOUNT_KEY, $amount['value']);
        }
        if (isset($amount['currency_code'])) {
            $payment->setAdditionalInformation(self::PP_ORDER_CURRENCY_KEY, $amount['currency_code']);
        }
    }
}

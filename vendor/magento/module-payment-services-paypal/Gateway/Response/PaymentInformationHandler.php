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

use InvalidArgumentException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentInformationHandler implements HandlerInterface
{
    public const PAYPAL_DEBUG_ID_KEY = 'paypal_debug_id';
    public const PAYER_EMAIL_KEY = 'payer_email';

    /**
     * Persists payment-information fields from the backend response onto the payment.
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
            throw new InvalidArgumentException('Payment data object should be provided');
        }

        $transaction = $response['mp-transaction'] ?? [];

        $paypalDebugId = $transaction[self::PAYPAL_DEBUG_ID_KEY] ?? null;
        $payerEmail = $transaction[self::PAYER_EMAIL_KEY] ?? null;

        if (empty($paypalDebugId) && empty($payerEmail)) {
            return;
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];
        $payment = $paymentDO->getPayment();

        if (!empty($paypalDebugId)) {
            $payment->setAdditionalInformation(self::PAYPAL_DEBUG_ID_KEY, $paypalDebugId);
        }

        if (!empty($payerEmail)) {
            $payment->setAdditionalInformation(self::PAYER_EMAIL_KEY, $payerEmail);
        }
    }
}

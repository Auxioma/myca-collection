<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

namespace Magento\PaymentServicesPaypal\Helper;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Psr\Log\LoggerInterface;

class PaymentSourceResponseProcessor
{
    public const AVS_CODE = 'avs_code';
    public const CVV_CODE = 'cvv_code';

    /**
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Parse and save processor response data into Payment
     *
     * @param array $transaction
     * @param InfoInterface $payment
     * @return void
     */
    public function parseProcessorResponseInformation(array $transaction, InfoInterface $payment): void
    {
        if (empty($transaction['processor_response'])) {
            $this->logger->warning(
                'No processor response found in payment source response in transaction',
                $transaction
            );
            return;
        }

        $processorResponse = $transaction['processor_response'];
        $avsCode = $processorResponse[self::AVS_CODE] ?? null;
        $cvvCode = $processorResponse[self::CVV_CODE] ?? null;

        $payment->setCcAvsStatus($avsCode);
        $payment->setCcCidStatus($cvvCode);

        if (!empty($avsCode)) {
            $payment->setAdditionalInformation(self::AVS_CODE, $avsCode);
        }

        if (!empty($cvvCode)) {
            $payment->setAdditionalInformation(self::CVV_CODE, $cvvCode);
        }
    }

    /**
     * Parse and save card information into the sales_order_payment
     *
     * @param array $transaction
     * @param InfoInterface $payment
     * @return void
     */
    public function saveCardInformation(array $transaction, InfoInterface $payment): void
    {
        if (!isset($transaction['payment_source_details'])) {
            $this->logger->warning('No payment source details found in payment source response', $transaction);
            return;
        }

        $card = $transaction['payment_source_details']['card']
            ?? $transaction['payment_source_details']['applePay']['card']
            ?? null;

        if ($card == null) {
            $this->logger->warning('No card details found in payment source response', $transaction);
            return;
        }

        $name = $card['name'] ?? null;
        $bin = isset($card['bin_details']['bin']) ?
            $this->encryptor->encrypt($card['bin_details']['bin']) :
            null;
        $lastDigits = $card['last_digits'] ?? null;
        $ccType = $card['brand'] ?? null;
        $expMonth = $card['card_expiry_month'] ?? null;
        $expYear = $card['card_expiry_year'] ?? null;

        $payment->setCcOwner($name);
        $payment->setCcNumberEnc($bin);
        $payment->setCcLast4($lastDigits);
        $payment->setCcType($ccType);
        $payment->setCcExpMonth($expMonth);
        $payment->setCcExpYear($expYear);

        $payment->setAdditionalInformation(OrderPaymentInterface::CC_OWNER, $name);
        $payment->setAdditionalInformation(OrderPaymentInterface::CC_LAST_4, $lastDigits);
        $payment->setAdditionalInformation(OrderPaymentInterface::CC_TYPE, $ccType);
        $payment->setAdditionalInformation(OrderPaymentInterface::CC_EXP_MONTH, $expMonth);
        $payment->setAdditionalInformation(OrderPaymentInterface::CC_EXP_YEAR, $expYear);
    }
}

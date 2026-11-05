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
namespace PayPal\Braintree\Gateway\Request;

use InvalidArgumentException;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Model\Lpm\Config;
use PayPal\Braintree\Model\Lpm\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;

class RefundDataBuilder implements BuilderInterface
{
    use Formatter;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();

        $amount = null;

        try {
            $amount = $this->formatPrice($this->subjectReader->readAmount($buildSubject));
        } catch (InvalidArgumentException $e) {
            $this->logger->critical($e->getMessage());
        }

        // Handle refund for Pay Upon Invoice
        $paymentMethod = $payment->getMethod();
        $fundingSource = $payment->getAdditionalInformation('fundingSource');
        if ($paymentMethod === ConfigProvider::METHOD_CODE
            && $fundingSource === Config::VALUE_PAY_UPON_INVOICE
            && $payment->getParentTransactionId() === null
        ) {
            $txnId = $payment->getLastTransId();
        } else {
            /**
             * we should remember that Payment sets Capture txn id of current Invoice into ParentTransactionId Field
             * We should also support previous implementations of Magento Braintree -
             * and cut off '-capture' postfix from transaction ID to support backward compatibility
             */
            $txnId = str_replace(
                '-' . TransactionInterface::TYPE_CAPTURE,
                '',
                $payment->getParentTransactionId()
            );
        }

        return [
            'transaction_id' => $txnId,
            PaymentDataBuilder::AMOUNT => $amount
        ];
    }
}

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

namespace Magento\PaymentServicesPaypal\Model\CustomCapture;

use InvalidArgumentException;
use LogicException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Psr\Log\LoggerInterface;

class TransactionMetadata
{
    private const KEY = 'payment_services_custom_capture';
    private const VERSION_ONE = 1;
    private const CURRENT_VERSION = self::VERSION_ONE;
    private const CACHE_DATA_KEY = 'payment_services_custom_capture_transaction_metadata';
    private const CACHE_RESOLVED_KEY = 'payment_services_custom_capture_transaction_metadata_resolved';
    private const REJECTION_MESSAGE = 'Persisted Over Capture metadata was rejected.';

    /**
     * @param Json $serializer
     * @param TransactionRepository $transactionRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Json $serializer,
        private readonly TransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Stage custom capture metadata for Magento's capture transaction.
     *
     * @param Invoice $invoice
     * @param Data $customCaptureData
     * @return void
     */
    public function stage(Invoice $invoice, Data $customCaptureData): void
    {
        $payment = $invoice->getOrder()->getPayment();
        if (!$payment instanceof Payment) {
            throw new LogicException('Cannot stage custom capture metadata without an order payment.');
        }

        $payment->setTransactionAdditionalInfo(
            self::KEY,
            $this->serializer->serialize(
                [
                    'version' => self::CURRENT_VERSION,
                    'amount' => $customCaptureData->getAmount(),
                    'base_amount' => $customCaptureData->getBaseAmount(),
                    'reason' => $customCaptureData->getReason(),
                ]
            )
        );
    }

    /**
     * Resolve custom capture metadata for a new or persisted invoice.
     *
     * @param Invoice $invoice
     * @return Data|null
     */
    public function extractFromInvoice(Invoice $invoice): ?Data
    {
        if (!$invoice->getId()) {
            return $this->extractTransientData($invoice);
        }

        if ($invoice->getData(self::CACHE_RESOLVED_KEY) === true) {
            $cached = $invoice->getData(self::CACHE_DATA_KEY);
            return $cached instanceof Data ? $cached : null;
        }

        $customCaptureData = $this->extractPersistedData($invoice);
        $invoice->setData(self::CACHE_DATA_KEY, $customCaptureData);
        $invoice->setData(self::CACHE_RESOLVED_KEY, true);

        return $customCaptureData;
    }

    /**
     * Extract custom capture data set on an unsaved invoice.
     *
     * @param Invoice $invoice
     * @return Data|null
     */
    private function extractTransientData(Invoice $invoice): ?Data
    {
        return $this->buildData(
            [
                'version' => self::CURRENT_VERSION,
                'amount' => $invoice->getData(Data::AMOUNT),
                'base_amount' => $invoice->getData(Data::BASE_AMOUNT),
                'reason' => $invoice->getData(Data::REASON),
            ]
        );
    }

    /**
     * Extract custom capture data from the invoice's capture transaction.
     *
     * @param Invoice $invoice
     * @return Data|null
     */
    private function extractPersistedData(Invoice $invoice): ?Data
    {
        $context = $this->getTransactionContext($invoice);
        if ($context === null) {
            return null;
        }

        $transaction = $this->transactionRepository->getByTransactionId(
            $context['transaction_id'],
            $context['payment_id'],
            $context['order_id']
        );
        if (!$transaction instanceof TransactionInterface
            || !$this->isMatchingCaptureTransaction($transaction, $context)
        ) {
            return null;
        }

        $data = $this->decodePayload($transaction->getAdditionalInformation(), $context);
        if ($data === null) {
            return null;
        }

        return $this->buildData($data, $context);
    }

    /**
     * Resolve the identity required to load an invoice capture transaction.
     *
     * @param Invoice $invoice
     * @return array{order_id: int, payment_id: int, transaction_id: string}|null
     */
    private function getTransactionContext(Invoice $invoice): ?array
    {
        $transactionId = (string) $invoice->getTransactionId();
        $order = $invoice->getOrder();
        $orderId = (int) $order->getId();
        $paymentId = (int) $order->getPayment()?->getId();
        if ($transactionId === '' || $orderId <= 0 || $paymentId <= 0) {
            return null;
        }

        return [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * Check that a transaction belongs to the invoice and is a capture.
     *
     * @param TransactionInterface $transaction
     * @param array $context
     * @return bool
     */
    private function isMatchingCaptureTransaction(TransactionInterface $transaction, array $context): bool
    {
        if ((string) $transaction->getTxnId() !== $context['transaction_id']
            || (int) $transaction->getPaymentId() !== $context['payment_id']
            || (int) $transaction->getOrderId() !== $context['order_id']
        ) {
            $this->logMetadataRejection('provenance_mismatch', $context);
            return false;
        }

        if ((string) $transaction->getTxnType() !== TransactionInterface::TYPE_CAPTURE) {
            $this->logMetadataRejection('transaction_type_mismatch', $context);
            return false;
        }

        return true;
    }

    /**
     * Decode a persisted custom capture payload.
     *
     * @param mixed $additionalInformation
     * @param array $context
     * @return array|null
     */
    private function decodePayload(mixed $additionalInformation, array $context): ?array
    {
        if (!is_array($additionalInformation) || !array_key_exists(self::KEY, $additionalInformation)) {
            return null;
        }

        $payload = $additionalInformation[self::KEY];
        if (!is_string($payload) || $payload === '') {
            $this->logMetadataRejection('invalid_payload_type', $context);
            return null;
        }

        try {
            $data = $this->serializer->unserialize($payload);
        } catch (InvalidArgumentException) {
            $this->logMetadataRejection('malformed_json', $context);
            return null;
        }

        if (!is_array($data)) {
            $this->logMetadataRejection('invalid_decoded_payload_type', $context);
            return null;
        }

        return $data;
    }

    /**
     * Build validated custom capture data from metadata.
     *
     * Parsers for previously released versions must be retained when a new writer version is added.
     * Unknown versions are rejected because their financial payload shape must not be inferred.
     *
     * @param array $data
     * @param array|null $logContext
     * @return Data|null
     */
    private function buildData(array $data, ?array $logContext = null): ?Data
    {
        if (!array_key_exists('version', $data)) {
            $this->logMetadataRejection('missing_version', $logContext);
            return null;
        }

        if ($data['version'] !== self::VERSION_ONE) {
            $this->logMetadataRejection('unsupported_version', $logContext);
            return null;
        }

        return $this->buildVersionOneData($data, $logContext);
    }

    /**
     * Build validated version-one custom capture data from metadata.
     *
     * @param array $data
     * @param array|null $logContext
     * @return Data|null
     */
    private function buildVersionOneData(array $data, ?array $logContext = null): ?Data
    {
        if (!$this->isValidAmount($data['amount'] ?? null)) {
            $this->logMetadataRejection('invalid_amount', $logContext);
            return null;
        }

        if (!$this->isValidAmount($data['base_amount'] ?? null)) {
            $this->logMetadataRejection('invalid_base_amount', $logContext);
            return null;
        }

        if (!$this->isValidReason($data['reason'] ?? null)) {
            $this->logMetadataRejection('invalid_reason', $logContext);
            return null;
        }

        return new Data(
            (float) $data['amount'],
            (float) $data['base_amount'],
            $data['reason']
        );
    }

    /**
     * Log a metadata rejection only when reading a persisted payload.
     *
     * @param string $failure
     * @param array|null $logContext
     * @return void
     */
    private function logMetadataRejection(string $failure, ?array $logContext): void
    {
        if ($logContext === null) {
            return;
        }

        $this->logger->warning(
            self::REJECTION_MESSAGE,
            $logContext + ['failure' => $failure]
        );
    }

    /**
     * Check whether a metadata reason is safe to display as plain text.
     *
     * @param mixed $reason
     * @return bool
     */
    private function isValidReason(mixed $reason): bool
    {
        return is_string($reason)
            && mb_strlen($reason, 'UTF-8') <= Data::MAX_REASON_LENGTH
            && strip_tags($reason) === $reason;
    }

    /**
     * Check whether a metadata amount is safe to display.
     *
     * @param mixed $amount
     * @return bool
     */
    private function isValidAmount(mixed $amount): bool
    {
        return (is_int($amount) || is_float($amount))
            && is_finite((float) $amount)
            && (float) $amount > 0.0;
    }
}

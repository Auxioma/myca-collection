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

use LogicException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Invoice;

class Applier
{
    /**
     * @param CommentFormatter $commentFormatter
     * @param TransactionMetadata $transactionMetadata
     */
    public function __construct(
        private readonly CommentFormatter $commentFormatter,
        private readonly TransactionMetadata $transactionMetadata
    ) {
    }

    /**
     * Apply custom capture data to a newly prepared invoice before Magento captures and persists it.
     *
     * This method intentionally does not save invoice, order, or payment entities. The standard
     * Magento invoice lifecycle owns capture and persistence after the invoice has been prepared.
     *
     * @param Invoice $invoice
     * @param Data $customCaptureData
     * @return void
     */
    public function apply(Invoice $invoice, Data $customCaptureData): void
    {
        if ($invoice->getId()) {
            throw new LogicException('Custom capture data cannot be applied to a persisted invoice.');
        }

        $existingAmount = $invoice->getData(Data::AMOUNT);
        if ($existingAmount !== null) {
            if ($this->isSameData($invoice, $customCaptureData)) {
                return;
            }

            throw new LogicException('Conflicting custom capture data has already been applied to this invoice.');
        }

        if ($invoice->getData(Data::BASE_AMOUNT) !== null || $invoice->getData(Data::REASON) !== null) {
            throw new LogicException('Incomplete custom capture data has already been applied to this invoice.');
        }

        $order = $invoice->getOrder();
        $comment = $this->commentFormatter->format(
            $order->formatPriceTxt($customCaptureData->getAmount()),
            $customCaptureData->getReason()
        );

        $invoice->setGrandTotal(
            $this->roundAmount((float) $invoice->getGrandTotal() + $customCaptureData->getAmount())
        );
        $invoice->setBaseGrandTotal(
            $this->roundAmount((float) $invoice->getBaseGrandTotal() + $customCaptureData->getBaseAmount())
        );
        $invoice->setData(Data::AMOUNT, $customCaptureData->getAmount());
        $invoice->setData(Data::BASE_AMOUNT, $customCaptureData->getBaseAmount());
        $invoice->setData(Data::REASON, $customCaptureData->getReason());
        $invoice->addComment($comment);
        $order->addCommentToStatusHistory($comment);
        $this->transactionMetadata->stage($invoice, $customCaptureData);
    }

    /**
     * Check whether the invoice already contains the same custom capture data.
     *
     * @param Invoice $invoice
     * @param Data $customCaptureData
     * @return bool
     */
    private function isSameData(Invoice $invoice, Data $customCaptureData): bool
    {
        return $this->roundAmount((float) $invoice->getData(Data::AMOUNT))
                === $this->roundAmount($customCaptureData->getAmount())
            && $this->roundAmount((float) $invoice->getData(Data::BASE_AMOUNT))
                === $this->roundAmount($customCaptureData->getBaseAmount())
            && (string) $invoice->getData(Data::REASON) === $customCaptureData->getReason();
    }

    /**
     * Round an amount to the precision used by Magento monetary values and the gateway payload.
     *
     * @param float $amount
     * @return float
     */
    private function roundAmount(float $amount): float
    {
        return round($amount, PriceCurrencyInterface::DEFAULT_PRECISION, PHP_ROUND_HALF_UP);
    }
}

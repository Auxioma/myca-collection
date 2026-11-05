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

namespace Magento\PaymentServicesPaypal\Block\Order\Invoice;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\PaymentServicesPaypal\Model\CustomCapture\TransactionMetadata;
use Magento\Sales\Model\Order\Invoice;

class CustomCaptureReason extends Template
{
    /**
     * @param Context $context
     * @param TransactionMetadata $transactionMetadata
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly TransactionMetadata $transactionMetadata,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Set invoice for reason rendering.
     *
     * @param Invoice $invoice
     * @return $this
     */
    public function setInvoice(Invoice $invoice): self
    {
        $this->setData('invoice', $invoice);
        return $this;
    }

    /**
     * Check whether an Additional Amount reason is available.
     *
     * @return bool
     */
    public function hasReason(): bool
    {
        return $this->getReason() !== '';
    }

    /**
     * Get custom capture reason for the current invoice.
     *
     * @return string
     */
    public function getReason(): string
    {
        $invoice = $this->getInvoice();
        if (!$invoice instanceof Invoice) {
            return '';
        }

        $customCapture = $this->transactionMetadata->extractFromInvoice($invoice);

        return $customCapture === null ? '' : trim($customCapture->getReason());
    }

    /**
     * Get invoice from block data or parent block.
     *
     * @return Invoice|null
     */
    private function getInvoice(): ?Invoice
    {
        $invoice = $this->getData('invoice');
        if ($invoice instanceof Invoice) {
            return $invoice;
        }

        $parent = $this->getParentBlock();
        if ($parent && method_exists($parent, 'getInvoice')) {
            $invoice = $parent->getInvoice();
            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        if ($parent && method_exists($parent, 'getSource')) {
            $invoice = $parent->getSource();
            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        return null;
    }
}

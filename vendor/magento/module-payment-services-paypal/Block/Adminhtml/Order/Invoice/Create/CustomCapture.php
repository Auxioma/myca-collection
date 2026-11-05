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

namespace Magento\PaymentServicesPaypal\Block\Adminhtml\Order\Invoice\Create;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Data;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Eligibility;
use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\Sales\Model\Order\Invoice;

/**
 * @api
 */
class CustomCapture extends Template
{
    private const INVOICE_UPDATE_TOTALS_URL = 'paymentservicespaypal/order_invoice/updateTotals';

    /**
     * @param Context $context
     * @param Eligibility $eligibility
     * @param InvoiceContext $invoiceContext
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Eligibility $eligibility,
        private readonly InvoiceContext $invoiceContext,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check whether Over Capture is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $invoice = $this->getInvoice();
        return $invoice !== null && $this->eligibility->isEligibleOrder($invoice->getOrder());
    }

    /**
     * Get the Update Totals URL.
     *
     * @return string
     */
    public function getUpdateTotalsUrl(): string
    {
        return $this->getUrl(
            self::INVOICE_UPDATE_TOTALS_URL,
            ['order_id' => $this->getInvoice() ? $this->getInvoice()->getOrderId() : null]
        );
    }

    /**
     * Get the current Additional Amount.
     *
     * @return string
     */
    public function getAmount(): string
    {
        $amount = $this->getInvoice() ? $this->getInvoice()->getData(Data::AMOUNT) : null;
        return $amount === null ? '' : (string) $amount;
    }

    /**
     * Get the current Additional Amount reason.
     *
     * @return string
     */
    public function getReason(): string
    {
        $reason = $this->getInvoice() ? $this->getInvoice()->getData(Data::REASON) : null;
        return $reason === null ? '' : (string) $reason;
    }

    /**
     * Get the prepared invoice from the current request context.
     *
     * @return Invoice|null
     */
    private function getInvoice(): ?Invoice
    {
        return $this->invoiceContext->getInvoice();
    }
}

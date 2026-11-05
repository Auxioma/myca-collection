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

namespace Magento\PaymentServicesPaypal\Plugin\CustomCapture;

use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\Sales\Block\Adminhtml\Items\AbstractItems;

class InvoiceItemsPlugin
{
    /**
     * @param InvoiceContext $invoiceContext
     */
    public function __construct(
        private readonly InvoiceContext $invoiceContext
    ) {
    }

    /**
     * Use the prepared invoice for the custom Update Totals request.
     *
     * @param AbstractItems $subject
     * @param callable $proceed
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetInvoice(AbstractItems $subject, callable $proceed): mixed
    {
        return $this->invoiceContext->getInvoice() ?? $proceed();
    }
}

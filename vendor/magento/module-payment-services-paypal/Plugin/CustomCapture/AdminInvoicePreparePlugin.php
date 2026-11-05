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

use Magento\Framework\App\Request\Http;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Applier;
use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Validator;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;

class AdminInvoicePreparePlugin
{
    private const SUPPORTED_ACTIONS = [
        'sales_order_invoice_new',
        'sales_order_invoice_save',
        'sales_order_invoice_updateqty',
        'paymentservicespaypal_order_invoice_updatetotals',
    ];

    /**
     * @param Http $request
     * @param Validator $validator
     * @param Applier $applier
     * @param InvoiceContext $invoiceContext
     */
    public function __construct(
        private readonly Http $request,
        private readonly Validator $validator,
        private readonly Applier $applier,
        private readonly InvoiceContext $invoiceContext
    ) {
    }

    /**
     * Apply custom capture amount to admin invoice previews and submit.
     *
     * @param InvoiceService $subject
     * @param InvoiceInterface $invoice
     * @param Order $order
     * @param array $orderItemsQtyToInvoice
     * @return InvoiceInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterPrepareInvoice(
        InvoiceService $subject,
        InvoiceInterface $invoice,
        Order $order,
        array $orderItemsQtyToInvoice = []
    ): InvoiceInterface {
        $fullActionName = strtolower((string) $this->request->getFullActionName());
        if (!$invoice instanceof Invoice || !in_array($fullActionName, self::SUPPORTED_ACTIONS, true)) {
            return $invoice;
        }

        $this->invoiceContext->setInvoice($invoice);

        $invoiceData = (array) $this->request->getParam('invoice', []);
        $customCaptureData = $this->validator->validateAndCreate(
            $invoiceData['custom_capture_amount'] ?? null,
            $invoiceData['custom_capture_amount_reason'] ?? null,
            $order,
            $this->validator->isOnlineCaptureCase($invoiceData['capture_case'] ?? null)
        );

        if ($customCaptureData !== null) {
            $this->applier->apply($invoice, $customCaptureData);
        }

        return $invoice;
    }
}

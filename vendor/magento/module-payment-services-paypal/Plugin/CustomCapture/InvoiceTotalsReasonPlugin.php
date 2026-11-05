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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\PaymentServicesPaypal\Block\Order\Invoice\CustomCaptureReason;
use Magento\Sales\Model\Order\Invoice;

class InvoiceTotalsReasonPlugin
{
    private const CUSTOMER_TEMPLATE = 'Magento_PaymentServicesPaypal::order/invoice/custom_capture_reason.phtml';

    /**
     * @param string $template
     */
    public function __construct(
        private readonly string $template = self::CUSTOMER_TEMPLATE
    ) {
    }

    /**
     * Append the custom capture reason after the invoice totals table.
     *
     * @param AbstractBlock $subject
     * @param string $html
     * @return string
     * @throws LocalizedException
     */
    public function afterToHtml(AbstractBlock $subject, string $html): string
    {
        if ($html === '' || !method_exists($subject, 'getSource')) {
            return $html;
        }

        $invoice = $subject->getSource();
        if (!$invoice instanceof Invoice || $subject->getLayout() === null) {
            return $html;
        }

        if (!method_exists($subject, 'getTotal') || $subject->getTotal('custom_capture_amount') === null) {
            return $html;
        }

        $reasonBlock = $subject->getLayout()->createBlock(CustomCaptureReason::class);
        if (!$reasonBlock instanceof CustomCaptureReason) {
            return $html;
        }

        $reasonBlock->setInvoice($invoice);
        $reasonBlock->setTemplate($this->template);

        return $html . $reasonBlock->toHtml();
    }
}

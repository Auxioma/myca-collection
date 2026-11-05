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

namespace Magento\PaymentServicesPaypal\Block\Order\Invoice\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\PaymentServicesPaypal\Model\CustomCapture\TransactionMetadata;
use Magento\Sales\Model\Order\Invoice;

/**
 * @api
 */
class CustomCapture extends Template
{
    /**
     * @param Context $context
     * @param TransactionMetadata $transactionMetadata
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        private readonly TransactionMetadata $transactionMetadata,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Add custom capture rows to invoice email totals.
     *
     * @return $this
     */
    public function initTotals(): self
    {
        $parent = $this->getParentBlock();
        $invoice = $parent ? $parent->getSource() : null;
        if (!$invoice instanceof Invoice) {
            return $this;
        }

        $customCapture = $this->transactionMetadata->extractFromInvoice($invoice);
        if ($customCapture === null) {
            return $this;
        }

        $parent->addTotalBefore(
            new DataObject(
                [
                    'code' => 'custom_capture_amount',
                    'value' => $customCapture->getAmount(),
                    'strong' => true,
                    'label' => __('Additional Amount*'),
                ]
            ),
            ['grand_total', 'grand_total_incl', 'base_grandtotal']
        );

        return $this;
    }
}

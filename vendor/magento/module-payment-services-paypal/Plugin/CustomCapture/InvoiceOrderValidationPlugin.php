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

use Magento\PaymentServicesPaypal\Model\CustomCapture\Applier;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Validator;
use Magento\Sales\Api\Data\InvoiceCommentCreationInterface;
use Magento\Sales\Api\Data\InvoiceCreationArgumentsInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Validation\InvoiceOrderInterface;

class InvoiceOrderValidationPlugin
{
    /**
     * @param Validator $validator
     * @param Applier $applier
     */
    public function __construct(
        private readonly Validator $validator,
        private readonly Applier $applier
    ) {
    }

    /**
     * Apply REST custom capture attributes before Magento validates and captures the invoice.
     *
     * @param InvoiceOrderInterface $subject
     * @param OrderInterface $order
     * @param InvoiceInterface $invoice
     * @param bool $capture
     * @param array $items
     * @param bool $notify
     * @param bool $appendComment
     * @param InvoiceCommentCreationInterface|null $comment
     * @param InvoiceCreationArgumentsInterface|null $arguments
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeValidate(
        InvoiceOrderInterface $subject,
        OrderInterface $order,
        InvoiceInterface $invoice,
        $capture = false,
        array $items = [],
        $notify = false,
        $appendComment = false,
        ?InvoiceCommentCreationInterface $comment = null,
        ?InvoiceCreationArgumentsInterface $arguments = null
    ): array {
        if (!$invoice instanceof Invoice) {
            return [$order, $invoice, $capture, $items, $notify, $appendComment, $comment, $arguments];
        }

        $amount = $this->getExtensionAttribute($arguments, 'getCustomCaptureAmount');
        if ($amount === null || $amount === '') {
            return [$order, $invoice, $capture, $items, $notify, $appendComment, $comment, $arguments];
        }

        $customCaptureData = $this->validator->validateAndCreate(
            $amount,
            $this->getExtensionAttribute($arguments, 'getCustomCaptureAmountReason'),
            $order,
            (bool) $capture
        );
        if ($customCaptureData !== null) {
            $this->applier->apply($invoice, $customCaptureData);
        }

        return [$order, $invoice, $capture, $items, $notify, $appendComment, $comment, $arguments];
    }

    /**
     * Read an optional custom capture extension attribute.
     *
     * @param InvoiceCreationArgumentsInterface|null $arguments
     * @param string $method
     * @return mixed
     */
    private function getExtensionAttribute(
        ?InvoiceCreationArgumentsInterface $arguments,
        string $method
    ): mixed {
        if ($arguments === null || $arguments->getExtensionAttributes() === null) {
            return null;
        }

        $extensionAttributes = $arguments->getExtensionAttributes();
        if (!method_exists($extensionAttributes, $method)) {
            return null;
        }

        return $extensionAttributes->{$method}();
    }
}

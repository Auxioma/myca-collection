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

class CommentFormatter
{
    /**
     * @param Validator $validator
     */
    public function __construct(
        private readonly Validator $validator
    ) {
    }

    /**
     * Format the persisted invoice/order comment.
     *
     * @param string $formattedAmount
     * @param string $reason
     * @return string
     */
    public function format(string $formattedAmount, string $reason = ''): string
    {
        $reason = $this->validator->normalizeReason($reason);
        $lines = [
            (string) __('Additional Amount: %1', $formattedAmount),
        ];

        if ($reason !== '') {
            $lines[] = (string) __('Reason for Additional Amount: %1', $reason);
        }

        return implode("\n", $lines);
    }
}

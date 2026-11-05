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

class Data
{
    public const MAX_REASON_LENGTH = 255;
    public const AMOUNT = 'custom_capture_amount';
    public const BASE_AMOUNT = 'base_custom_capture_amount';
    public const REASON = 'custom_capture_amount_reason';

    /**
     * @param float $amount
     * @param float $baseAmount
     * @param string $reason
     */
    public function __construct(
        private readonly float $amount,
        private readonly float $baseAmount,
        private readonly string $reason
    ) {
    }

    /**
     * Get the order-currency Additional Amount.
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Get the base-currency Additional Amount.
     *
     * @return float
     */
    public function getBaseAmount(): float
    {
        return $this->baseAmount;
    }

    /**
     * Get the Additional Amount reason.
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}

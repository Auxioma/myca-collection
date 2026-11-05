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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;

class Validator
{
    /**
     * @param Eligibility $eligibility
     */
    public function __construct(
        private readonly Eligibility $eligibility
    ) {
    }

    /**
     * Validate and normalize request/API values before creating canonical custom capture data.
     *
     * @param mixed $amount
     * @param mixed $reason
     * @param OrderInterface $order
     * @param bool $isOnlineCapture
     * @return Data|null
     * @throws LocalizedException
     */
    public function validateAndCreate(
        mixed $amount,
        mixed $reason,
        OrderInterface $order,
        bool $isOnlineCapture
    ): ?Data {
        $amount = $this->normalizeAmount($amount);
        if ($amount === null) {
            return null;
        }

        $this->validateCaptureAvailability($order, $isOnlineCapture);
        $reason = $this->validateAndNormalizeReason($reason);

        return new Data($amount, $this->convertToBaseAmount($amount, $order), $reason);
    }

    /**
     * Normalize a supplied amount to the supported monetary precision.
     *
     * @param mixed $amount
     * @return float|null
     * @throws LocalizedException
     */
    private function normalizeAmount(mixed $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (!is_numeric($amount) || !is_finite((float) $amount)) {
            throw new LocalizedException(__('Additional Amount must be a valid number.'));
        }

        $amount = (float) $amount;
        if ($amount < 0) {
            throw new LocalizedException(__('Additional Amount must not be negative.'));
        }

        $amount = $this->roundAmount($amount);
        if ($amount <= 0.0) {
            throw new LocalizedException(__('Additional Amount must be at least 0.01.'));
        }

        return $amount;
    }

    /**
     * Validate that custom capture is available for the current invoice flow.
     *
     * @param OrderInterface $order
     * @param bool $isOnlineCapture
     * @return void
     * @throws LocalizedException
     */
    private function validateCaptureAvailability(OrderInterface $order, bool $isOnlineCapture): void
    {
        if (!$isOnlineCapture) {
            throw new LocalizedException(__('Additional Amount is only available for online capture.'));
        }

        if (!$this->eligibility->isEligibleOrder($order)) {
            throw new LocalizedException(__('Additional Amount is not available for this order.'));
        }
    }

    /**
     * Validate and normalize an optional plain-text reason.
     *
     * @param mixed $reason
     * @return string
     * @throws LocalizedException
     */
    private function validateAndNormalizeReason(mixed $reason): string
    {
        if ($reason !== null && !is_string($reason)) {
            throw new LocalizedException(__('Reason for Additional Amount must be a valid string.'));
        }

        $reason = $this->normalizeReason($reason ?? '');
        if (mb_strlen($reason, 'UTF-8') > Data::MAX_REASON_LENGTH) {
            throw new LocalizedException(
                __('Reason for Additional Amount must be %1 characters or fewer.', Data::MAX_REASON_LENGTH)
            );
        }

        return $reason;
    }

    /**
     * Check if admin capture case is online.
     *
     * @param string|null $captureCase
     * @return bool
     */
    public function isOnlineCaptureCase(?string $captureCase): bool
    {
        return $captureCase === Invoice::CAPTURE_ONLINE;
    }

    /**
     * Normalize reason to one line for comments and email extraction.
     *
     * @param string $reason
     * @return string
     */
    public function normalizeReason(string $reason): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($reason)));
    }

    /**
     * Convert order-currency custom amount to base currency.
     *
     * @param float $amount
     * @param OrderInterface $order
     * @return float
     */
    private function convertToBaseAmount(float $amount, OrderInterface $order): float
    {
        $baseCurrencyCode = (string) $order->getBaseCurrencyCode();
        $orderCurrencyCode = (string) $order->getOrderCurrencyCode();
        if ($baseCurrencyCode !== '' && $baseCurrencyCode === $orderCurrencyCode) {
            return $this->roundAmount($amount);
        }

        $rate = (float) $order->getBaseToOrderRate();
        if (!is_finite($rate) || $rate <= 0.0) {
            throw new LocalizedException(
                __('Cannot calculate the base Additional Amount because the currency rate is invalid.')
            );
        }

        $baseAmount = $this->roundAmount($amount / $rate);
        if (!is_finite($baseAmount) || $baseAmount <= 0.0) {
            throw new LocalizedException(
                __('Cannot calculate the base Additional Amount because the currency rate is invalid.')
            );
        }

        return $baseAmount;
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

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

namespace PayPal\BraintreeCustomerBalance\Plugin\Request\Data\Helper;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use PayPal\Braintree\Model\Request\Data\Helper\Totals;

/**
 * This plugin is to calculate the Store Credit as Discount for Adobe Commerce
 */
class CustomerBalanceTotalsPlugin
{
    /**
     * Manage discount amount totals
     *
     * @param Totals $subject
     * @param float $discount
     * @param CartInterface $quote
     * @return float
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetDiscountAmount(
        Totals $subject,
        float $discount,
        CartInterface $quote
    ): float {
        if ($quote->getBaseCustomerBalAmountUsed()) {
            $discount += $quote->getBaseCustomerBalAmountUsed();
        }
        return $discount;
    }
}

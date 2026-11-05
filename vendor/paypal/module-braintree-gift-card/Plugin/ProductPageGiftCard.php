<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
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

namespace PayPal\BraintreeGiftCard\Plugin;

use PayPal\Braintree\Block\Paypal\ProductPage;

class ProductPageGiftCard
{
    public const TYPE_GIFTCARD = 'giftcard';

    /**
     * Get amount for Gift card product
     *
     * @param ProductPage $subject
     * @param float $result
     * @return float
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAmount(
        ProductPage $subject,
        float $result
    ): float {
        $product = $subject->getProduct();

        if ($product->getTypeId() === self::TYPE_GIFTCARD) {
            $giftCardAmounts = $product->getGiftcardAmounts();
            if (!empty($giftCardAmounts)) {
                return (float) min(array_column($giftCardAmounts, 'price'));
            }

            // If custom amounts allowed or enabled
            if ($product->getAllowOpenAmount()) {
                return (float) $product->getOpenAmountMin();
            }
        }

        return $result;
    }
}

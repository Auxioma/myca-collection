<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Config\ApplePay;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const KEY_ACTIVE = 'active';
    public const KEY_MERCHANT_NAME = 'merchant_name';
    public const KEY_DISPLAY_ON_PDP = 'display_on_pdp';
    public const KEY_DISPLAY_ON_CART = 'display_on_cart';
    public const KEY_DISPLAY_ON_SHIPPING = 'display_on_shipping';
    public const KEY_SORT_ORDER = 'sort_order';

    /**
     * Get Payment configuration status
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE);
    }

    /**
     * Get merchant name to display
     *
     * @return string
     */
    public function getMerchantName(): string
    {
        return $this->getValue(self::KEY_MERCHANT_NAME);
    }

    /**
     * Display Apple Pay button on Product Page
     *
     * @return bool
     */
    public function displayApplePayButtonOnPdp(): bool
    {
        return (bool) $this->getValue(self::KEY_DISPLAY_ON_PDP);
    }

    /**
     * Display Apple Pay button on Mini Cart and Cart Page
     *
     * @return bool
     */
    public function displayApplePayButtonOnCart(): bool
    {
        return (bool) $this->getValue(self::KEY_DISPLAY_ON_CART);
    }

    /**
     * Display Apple Pay button on Top of checkout in Shipping step
     *
     * @return bool
     */
    public function displayApplePayButtonOnShipping(): bool
    {
        return (bool) $this->getValue(self::KEY_DISPLAY_ON_SHIPPING);
    }

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return (int) $this->getValue(self::KEY_SORT_ORDER);
    }
}

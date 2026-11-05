<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Gateway\Config\GooglePay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Model\Adminhtml\Source\GooglePayBtnColor;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const KEY_ACTIVE = 'active';
    public const KEY_CC_TYPES = 'cctypes';
    public const KEY_BTN_COLOR = 'btn_color';
    public const KEY_MERCHANT_ID = 'merchant_id';
    public const KEY_SKIP_ORDER_REVIEW_STEP = 'skip_order_review_step';
    public const KEY_DISPLAY_ON_PDP = 'display_on_pdp';
    public const KEY_DISPLAY_ON_CART = 'display_on_cart';
    public const KEY_DISPLAY_ON_SHIPPING = 'display_on_shipping';
    public const KEY_SORT_ORDER = 'sort_order';

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param BraintreeConfig $braintreeConfig
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        private readonly BraintreeConfig $braintreeConfig,
        ?string $methodCode = null,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

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
     * Get Google Pay Merchant ID
     *
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->getValue(self::KEY_MERCHANT_ID);
    }

    /**
     * Get BTN Color
     *
     * @return int
     */
    public function getBtnColor(): int
    {
        $color = $this->getValue(self::KEY_BTN_COLOR);
        if ($color == GooglePayBtnColor::OPTION_WHITE || $color == GooglePayBtnColor::OPTION_BLACK) {
            return (int) $color;
        }

        return GooglePayBtnColor::OPTION_WHITE;
    }

    /**
     * Get allowed payment card types
     *
     * @return array
     */
    public function getAvailableCardTypes(): array
    {
        $ccTypes = $this->getValue(self::KEY_CC_TYPES);

        return !empty($ccTypes) ? explode(',', $ccTypes) : [];
    }

    /**
     * Can skip order review step
     *
     * @return bool
     */
    public function skipOrderReviewStep(): bool
    {
        return (bool) $this->getValue(self::KEY_SKIP_ORDER_REVIEW_STEP);
    }

    /**
     * Map Braintree Environment setting
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getEnvironment(): string
    {
        if ($this->braintreeConfig->getEnvironment() !== 'production') {
            return 'TEST';
        }

        return 'PRODUCTION';
    }

    /**
     * Display Google Pay button on Product Page
     *
     * @return bool
     */
    public function displayGooglePayButtonOnPdp(): bool
    {
        return (bool) $this->getValue(self::KEY_DISPLAY_ON_PDP);
    }

    /**
     * Display Google Pay button on Mini Cart and Cart Page
     *
     * @return bool
     */
    public function displayGooglePayButtonOnCart(): bool
    {
        return (bool) $this->getValue(self::KEY_DISPLAY_ON_CART);
    }

    /**
     * Display Google Pay button on Top of checkout in Shipping step
     *
     * @return bool
     */
    public function displayGooglePayButtonOnShipping(): bool
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

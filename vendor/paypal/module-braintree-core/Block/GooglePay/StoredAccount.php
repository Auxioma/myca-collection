<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

namespace PayPal\Braintree\Block\GooglePay;

use Magento\Catalog\Block\ShortcutInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Block\GooglePay\Shortcut\Button;

/**
 * @api
 * @since 100.0.2
 */
class StoredAccount extends Button implements ShortcutInterface
{
    /**
     * Always return true as enabled check is handled in view model.
     *
     * @return bool
     * @see \PayPal\Braintree\Block\GooglePay\Shortcut\Button::isActive
     */
    public function isActive(): bool
    {
        return true;
    }

    /**
     * Currency code
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode(): string
    {
        return $this->_storeManager->getStore()->getBaseCurrency()->getCurrencyCode();
    }
}

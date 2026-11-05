<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\ApplePay\Shortcut;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Block\ApplePay\AbstractButton;
use Magento\Catalog\Block\ShortcutInterface;

class Button extends AbstractButton implements ShortcutInterface
{
    private const ALIAS_ELEMENT_INDEX = 'alias';
    private const BUTTON_ELEMENT_INDEX = 'button_id';

    /**
     * Check if Apple Pay express enabled for mini-cart and cart
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isActive(): bool
    {
        return $this->applePayConfig->isActive()
            && $this->applePayConfig->displayApplePayButtonOnCart()
            && ($this->getAmount() > 0);
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return $this->getData(self::ALIAS_ELEMENT_INDEX);
    }

    /**
     * Get container id
     *
     * @return string
     */
    public function getContainerId(): string
    {
        return $this->getData(self::BUTTON_ELEMENT_INDEX);
    }

    /**
     * Get extra class name
     *
     * @return string
     */
    public function getExtraClassname(): string
    {
        return $this->getIsCart() ? 'cart' : 'minicart';
    }
}

<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace PayPal\Braintree\Block;

use Magento\Catalog\Block\ShortcutInterface;
use Magento\Framework\View\Element\Template;

class Methods extends Template implements ShortcutInterface
{
    /**
     * Return alias
     *
     * @return string
     */
    public function getAlias(): string
    {
        return '';
    }
}

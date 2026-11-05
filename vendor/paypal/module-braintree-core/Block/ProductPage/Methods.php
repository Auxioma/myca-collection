<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace PayPal\Braintree\Block\ProductPage;

use Magento\Framework\View\Element\Template;

/**
 * @api
 * @since 100.0.2
 */
class Methods extends Template
{
    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        $blocks = [];
        $methods = $this->getChildNames();
        foreach ($methods as $childName) {
            $blocks[] = $this->getChildBlock($childName);
        }
        uasort(
            $blocks,
            function ($a, $b) {
                return (int)$a->getData('sort_order') - (int)$b->getData('sort_order');
            }
        );
        $output = '<div class="braintree-express-container braintree-express-container-pdp">';
        foreach ($blocks as $block) {
            $output .= $block->toHtml();
        }
        $output .= '</div>';
        return $output;
    }
}

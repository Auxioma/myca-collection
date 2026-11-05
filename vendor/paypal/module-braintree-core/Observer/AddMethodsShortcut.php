<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace PayPal\Braintree\Observer;

use Magento\Checkout\Block\QuoteShortcutButtons;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Block\ApplePay\Shortcut\Button as ApplePayButton;
use PayPal\Braintree\Block\Methods;
use PayPal\Braintree\Block\GooglePay\Shortcut\Button as GooglePayButton;
use PayPal\Braintree\Block\Paypal\Button as PayPalButton;
use PayPal\Braintree\Gateway\Config\ApplePay\Config as ApplePayConfig;
use PayPal\Braintree\Gateway\Config\GooglePay\Config as GooglePayConfig;
use PayPal\Braintree\Gateway\Config\PayPal\Config as PayPalConfig;

class AddMethodsShortcut implements ObserverInterface
{
    /**
     * @param ApplePayConfig $applePayConfig
     * @param GooglePayConfig $googlePayConfig
     * @param PayPalConfig $payPalConfig
     */
    public function __construct(
        private readonly ApplePayConfig $applePayConfig,
        private readonly GooglePayConfig $googlePayConfig,
        private readonly PayPalConfig $payPalConfig
    ) {
    }

    /**
     * Add express methods shortcut
     *
     * @param Observer $observer
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        if ($observer->getData('is_catalog_product')) {
            return;
        }

        $blocks = [];
        $shortcutButtons = $observer->getEvent()->getContainer();

        // PayPal
        if ($this->payPalConfig->isActive()) {
            $blocks[] = $shortcutButtons->getLayout()->createBlock(PayPalButton::class);
        }
        // Apple Pay
        if ($this->applePayConfig->isActive()) {
            $blocks[] = $shortcutButtons->getLayout()->createBlock(ApplePayButton::class);
        }
        // Google Pay
        if ($this->googlePayConfig->isActive()) {
            $blocks[] = $shortcutButtons->getLayout()->createBlock(GooglePayButton::class);
        }

        uasort(
            $blocks,
            function ($a, $b) {
                return (int)$a->getData('sort_order') - (int)$b->getData('sort_order');
            }
        );

        if (count($blocks)) {
            $container = $shortcutButtons->getLayout()->createBlock(Methods::class);

            foreach ($blocks as $block) {
                $block->setIsCart(get_class($shortcutButtons) === QuoteShortcutButtons::class);
                $container->setChild($block->getData('alias'), $block);
            }

            $shortcutButtons->addShortcut($container);
        }
    }
}

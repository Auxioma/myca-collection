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

namespace PayPal\Braintree\Plugin;

use Magento\Checkout\Block\Cart\Sidebar;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Gateway\Config\PayPalPayLater\Config as PayLaterConfig;
use PayPal\Braintree\Model\Ui\ConfigProvider;

class PayLaterMessageConfig
{
    /**
     * @param Config $config
     * @param ConfigProvider $configProvider
     * @param PayLaterConfig $payLaterConfig
     * @param StoreManagerInterface $storageManager
     */
    public function __construct(
        protected readonly Config $config,
        protected readonly ConfigProvider $configProvider,
        protected readonly PayLaterConfig $payLaterConfig,
        protected readonly StoreManagerInterface $storageManager
    ) {
    }

    /**
     * Add Pay Later message configuration.
     *
     * @param Sidebar $subject
     * @param array $result
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetConfig(Sidebar $subject, array $result): array
    {
        if ($this->config->isActive() && $this->payLaterConfig->isMessageActive('cart')) {
            $result['payPalBraintreeClientToken'] = $this->configProvider->getClientToken();
            $result['payPalBraintreePaylaterMessageConfig'] = $this->config->getMessageStyles('cart');
            $result['paypalBraintreeCurrencyCode'] = $this->storageManager->getStore()->getCurrentCurrencyCode();
        }

        return $result;
    }
}

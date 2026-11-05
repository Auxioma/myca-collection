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

namespace PayPal\Braintree\ViewModel;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Braintree\Model\GooglePay\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;
use PayPal\Braintree\Gateway\Config\GooglePayVault\Config as VaultConfig;

/**
 * ViewModel Methods for GooglePay vaulting in the customer account area
 */
class BraintreeGooglePayConfig implements ArgumentInterface
{
    /**
     * @var array
     */
    private array $config = [];

    /**
     * @param ConfigProvider $configProvider
     * @param VaultConfig $vaultConfig
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly VaultConfig $vaultConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get whether PayPal is active or not.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->getConfig()['isActive'];
    }

    /**
     * Check whether GooglePay Vault enabled or not
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function isGooglePayVaultEnabled(): bool
    {
        return $this->vaultConfig->isActive();
    }

    /**
     * Get Client token
     *
     * @return string|null
     */
    public function getClientToken(): ?string
    {
        return $this->getConfig()['clientToken'] ?? null;
    }

    /**
     * Get the payment method title.
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->getConfig()['title'] ?? null;
    }

    /**
     * Get the merchant's name.
     *
     * @return string|null
     */
    public function getMerchantName(): ?string
    {
        return $this->getConfig()['merchantName'] ?? null;
    }

    /**
     * Get the available credit card types.
     *
     * @return array
     */
    public function getAvailableCardTypes(): array
    {
        return $this->getConfig()['cardTypes'] ?? [];
    }

    /**
     * Get the current environment.
     *
     * @return string|null
     */
    public function getEnvironment(): ?string
    {
        return $this->getConfig()['environment'] ?? null;
    }

    /**
     * Get the merchant's country.
     *
     * @return string|null
     */
    public function getMerchantCountry(): ?string
    {
        return $this->getConfig()['merchantCountry'] ?? null;
    }

    /**
     * Get the locale.
     *
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->getConfig()['locale'] ?? null;
    }

    /**
     * Get the PayPal icon data array, otherwise null.
     *
     * @return array|null
     */
    public function getIcon(): ?array
    {
        return $this->getConfig()['paymentIcon'] ?? null;
    }

    /**
     * Get the PayPal Icon style.
     *
     * @return array|null
     */
    public function getStyle(): ?array
    {
        return $this->getConfig()['style'] ?? null;
    }

    /**
     * Get card icons
     *
     * @return array|null
     */
    public function getCardIcons(): ?array
    {
        return $this->getConfig()['icons'] ?? null;
    }

    /**
     * Get the Braintree PayPal config settings.
     *
     * @return array
     */
    private function getConfig(): array
    {
        if (!empty($this->config)) {
            return $this->config;
        }

        try {
            $config = $this->configProvider->getConfig();

            // If config empty, set flag to false, otherwise, load full config.
            $this->config = empty($config) ? ['isActive' => false] : $config['payment'][ConfigProvider::METHOD_CODE];

            return $this->config;
        } catch (LocalizedException $ex) {
            $this->logger->error('Failed to get Braintree GooglePay Config: ' . $ex->getMessage(), [
                'class' => BraintreeGooglePayConfig::class
            ]);

            $this->config = ['isActive' => false];

            return $this->config;
        }
    }

    /**
     * Get store code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }
}

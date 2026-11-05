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

namespace PayPal\Braintree\Gateway\Config\GooglePayVault;

use Magento\Payment\Gateway\Config\Config as MagentoPaymentGatewayConfig;
use PayPal\Braintree\Gateway\Config\GooglePay\Config as GooglePayGatewayConfig;
use PayPal\Braintree\Model\StoreConfigResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;

class Config extends MagentoPaymentGatewayConfig
{
    private const KEY_ACTIVE = 'active';

    /**
     * Config constructor.
     *
     * @param StoreConfigResolver $storeConfigResolver
     * @param GooglePayGatewayConfig $googlePayConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        private readonly StoreConfigResolver $storeConfigResolver,
        private readonly GooglePayGatewayConfig $googlePayConfig,
        ScopeConfigInterface $scopeConfig,
        ?string $methodCode = null,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    /**
     * Validate whether GooglePay Vault is active.
     *
     * Should be active if both GooglePay is active as a payment method & also GooglePay vault config is set to active.
     *
     * @param int|null $storeId
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function isActive(?int $storeId = null): bool
    {
        if ($storeId === null) {
            $storeId = $this->storeConfigResolver->getStoreId();
        }

        // Type casting if fetched from resolved to avoid some observed scenarios when it's not int.
        if ($storeId !== null) {
            $storeId = (int) $storeId;
        }

        return $this->googlePayConfig->isActive()
            && (bool) $this->getValue(self::KEY_ACTIVE, $storeId) === true;
    }
}

<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\Vault;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use PayPal\Braintree\Gateway\Config\PayPalVault\Config as BraintreePayPalVaultConfig;
use PayPal\Braintree\Gateway\Config\Vault\Config as BraintreeVaultConfig;
use PayPal\Braintree\Model\Ui\ConfigProvider as BraintreeConfigProvider;
use PayPal\Braintree\Model\Ui\PayPal\ConfigProvider as BraintreePayPalConfigProvider;
use PayPal\Braintree\Gateway\Config\GooglePayVault\Config as BraintreeGooglePayVaultConfig;
use PayPal\Braintree\Model\GooglePay\Ui\ConfigProvider as BraintreeGooglePayConfigProvider;

class IsAddPaymentToVaultEnabled implements IsAddPaymentToVaultEnabledInterface
{
    /**
     * @var BraintreeVaultConfig
     */
    private BraintreeVaultConfig $braintreeVaultConfig;

    /**
     * @var BraintreePayPalVaultConfig
     */
    private BraintreePayPalVaultConfig $braintreePayPalVaultConfig;

    /**
     * @var BraintreeGooglePayVaultConfig
     */
    private BraintreeGooglePayVaultConfig $braintreeGooglePayVaultConfig;

    /**
     * @param BraintreeVaultConfig $braintreeVaultConfig
     * @param BraintreePayPalVaultConfig $braintreePayPalVaultConfig
     * @param BraintreeGooglePayVaultConfig $braintreeGooglePayConfig
     */
    public function __construct(
        BraintreeVaultConfig $braintreeVaultConfig,
        BraintreePayPalVaultConfig $braintreePayPalVaultConfig,
        BraintreeGooglePayVaultConfig $braintreeGooglePayConfig,
    ) {
        $this->braintreeVaultConfig = $braintreeVaultConfig;
        $this->braintreePayPalVaultConfig = $braintreePayPalVaultConfig;
        $this->braintreeGooglePayVaultConfig = $braintreeGooglePayConfig;
    }

    /**
     * Is adding payment method to vault enabled.
     *
     * @param string $paymentMethod
     * @param int|null $storeId
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws InvalidArgumentException
     */
    public function execute(string $paymentMethod, ?int $storeId = null): bool
    {
        return match ($paymentMethod) {
            BraintreeConfigProvider::CODE => $this->braintreeVaultConfig->isActive($storeId),
            BraintreePayPalConfigProvider::PAYPAL_CODE => $this->braintreePayPalVaultConfig->isActive($storeId),
            BraintreeGooglePayConfigProvider::METHOD_CODE => $this->braintreeGooglePayVaultConfig->isActive($storeId),
            default => throw new InvalidArgumentException(__('Payment method %1 cannot be vaulted', $paymentMethod))
        };
    }
}

<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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

namespace PayPal\Braintree\Model\ApplePay\Ui;

use Magento\Framework\Exception\LocalizedException;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Gateway\Config\ApplePay\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Tax\Helper\Data as TaxHelper;

class ConfigProvider implements ConfigProviderInterface
{
    public const METHOD_CODE = 'braintree_applepay';
    public const METHOD_VAULT_CODE = 'braintree_applepay_vault';

    /**
     * @var string
     */
    private string $clientToken = '';

    /**
     * @var array
     */
    private array $icon = [];

    /**
     * ConfigProvider constructor.
     *
     * @param Config $config
     * @param BraintreeAdapter $adapter
     * @param Repository $assetRepo
     * @param BraintreeConfig $braintreeConfig
     * @param TaxHelper $taxHelper
     */
    public function __construct(
        private readonly Config $config,
        private readonly BraintreeAdapter $adapter,
        private readonly Repository $assetRepo,
        private readonly BraintreeConfig $braintreeConfig,
        private readonly TaxHelper $taxHelper
    ) {
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     * @throws LocalizedException
     */
    public function getConfig(): array
    {
        if (!$this->config->isActive()) {
            return [];
        }

        return [
            'payment' => [
                self::METHOD_CODE => [
                    'isActive' => $this->config->isActive(),
                    'isActiveShipping' => $this->isActiveShipping(),
                    'clientToken' => $this->getClientToken(),
                    'merchantName' => $this->config->getMerchantName(),
                    'paymentMarkSrc' => $this->getPaymentMarkSrc(),
                    'priceIncludesTax' => $this->taxHelper->priceIncludesTax(),
                    'vaultCode' => self::METHOD_VAULT_CODE
                ]
            ]
        ];
    }

    /**
     * Generate a new client token if necessary
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): ?string
    {
        if (empty($this->clientToken)) {
            $params = [];

            $merchantAccountId = $this->braintreeConfig->getMerchantAccountId();
            if (!empty($merchantAccountId)) {
                $params[PaymentDataBuilder::MERCHANT_ACCOUNT_ID] = $merchantAccountId;
            }

            $this->clientToken = $this->adapter->generate($params);
        }

        return $this->clientToken;
    }

    /**
     * Get the url to the payment mark image
     *
     * @return string
     */
    public function getPaymentMarkSrc(): string
    {
        return $this->assetRepo->getUrl('PayPal_Braintree::images/applepaymark.svg');
    }

    /**
     * Get icons for available payment methods
     *
     * @return array
     * @throws LocalizedException
     */
    public function getIcon(): array
    {
        if (!empty($this->icon)) {
            return $this->icon;
        }

        $asset = $this->assetRepo->createAsset(
            'PayPal_Braintree::images/applepaymark.svg',
            ['_secure' => true]
        );

        $this->icon = [
            'url' => $asset->getUrl(),
            'width' => 47,
            'height' => 30,
            'title' => __('Apple Pay'),
        ];

        return $this->icon;
    }

    /**
     * Is Apple Pay enabled for top of checkout
     *
     * @return bool
     */
    public function isActiveShipping(): bool
    {
        return (bool) $this->config->isActive() && $this->config->displayApplePayButtonOnShipping();
    }
}

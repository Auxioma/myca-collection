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
namespace PayPal\Braintree\Model\Ui;

use Braintree\Result\Error;
use Braintree\Result\Successful;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\View\Asset\Source;
use Magento\Payment\Model\CcConfig;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Config\PayPal\Config as PayPalConfig;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'braintree';
    public const CC_VAULT_CODE = 'braintree_cc_vault';

    /**
     * ConfigProvider constructor.
     *
     * @param Config $config
     * @param PayPalConfig $payPalConfig
     * @param BraintreeAdapter $adapter
     * @param CcConfig $ccConfig
     * @param Source $assetSource
     * @param File $fileIo
     * @param string|null $clientToken
     * @param array $icons
     */
    public function __construct(
        private readonly Config $config,
        private readonly PayPalConfig $payPalConfig,
        private readonly BraintreeAdapter $adapter,
        private readonly CcConfig $ccConfig,
        private readonly Source $assetSource,
        private readonly File $fileIo,
        private ?string $clientToken = '',
        private array $icons = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        if (!$this->config->isActive()) {
            return [];
        }

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive(),
                    'clientToken' => $this->getClientToken(),
                    'ccTypesMapper' => $this->config->getCcTypesMapper(),
                    'countrySpecificCardTypes' => $this->config->getCountrySpecificCardTypeConfig(),
                    'availableCardTypes' => $this->config->getAvailableCardTypes(),
                    'useCvv' => $this->config->isCvvEnabled(),
                    'environment' => $this->config->getEnvironment(),
                    'merchantId' => $this->config->getMerchantId(),
                    'ccVaultCode' => self::CC_VAULT_CODE,
                    'style' => [
                        'shape' => $this->payPalConfig->getButtonShape(PayPalConfig::BUTTON_AREA_CHECKOUT),
                        'color' => $this->payPalConfig->getButtonColor(PayPalConfig::BUTTON_AREA_CHECKOUT)
                    ],
                    'disabledFunding' => [
                        'card' => $this->payPalConfig->isFundingOptionCardDisabled(),
                        'elv' => $this->payPalConfig->isFundingOptionElvDisabled()
                    ],
                    'icons' => $this->getIcons()
                ]
            ]
        ];
    }

    /**
     * Generate a new client token if necessary
     *
     * @param int|null $storeId
     * @return Error|Successful|string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(?int $storeId = null): Error|Successful|string|null
    {
        if (empty($this->clientToken)) {
            $params = [];

            $merchantAccountId = $this->config->getMerchantAccountId($storeId);
            if (!empty($merchantAccountId)) {
                $params[PaymentDataBuilder::MERCHANT_ACCOUNT_ID] = $merchantAccountId;
            }

            $this->clientToken = $this->adapter->generate($params);
        }

        return $this->clientToken;
    }

    /**
     * Get icons for available payment methods
     *
     * @return array
     */
    public function getIcons(): array
    {
        if (!empty($this->icons)) {
            return $this->icons;
        }

        $types = $this->ccConfig->getCcAvailableTypes();
        $types['NONE'] = '';

        foreach (array_keys($types) as $code) {
            if (!array_key_exists($code, $this->icons)) {
                $asset = $this->ccConfig->createAsset('PayPal_Braintree::images/cc/' . strtoupper($code) . '.png');
                if ($asset) {
                    $placeholder = $this->assetSource->findSource($asset);
                    if ($placeholder) {
                        $imageData = $this->fileIo->read($asset->getSourceFile());
                        $imageSize = getimagesizefromstring($imageData);
                        if ($imageSize !== false) {
                            list($width, $height) = $imageSize;
                            $this->icons[$code] = [
                                'url' => $asset->getUrl(),
                                'alt' => $code,
                                'width' => $width,
                                'height' => $height
                            ];
                        }
                    }
                }
            }
        }

        return $this->icons;
    }

    /**
     * Retrieve CVV tooltip image url
     *
     * @return string
     */
    public function getCvvImageUrl(): string
    {
        return $this->ccConfig->getCvvImageUrl();
    }
}

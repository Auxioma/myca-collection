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

namespace PayPal\Braintree\Model\GooglePay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Request\PaymentDataBuilder;
use PayPal\Braintree\Gateway\Config\GooglePay\Config;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Payment\Model\CcConfig;
use Magento\Framework\View\Asset\Source as AssetSource;

class ConfigProvider implements ConfigProviderInterface
{
    public const METHOD_CODE = 'braintree_googlepay';
    public const METHOD_VAULT_CODE = 'braintree_googlepay_vault';

    /**
     * @var string
     */
    private string $clientToken = '';

    /**
     * @var string
     */
    private string $fileId = 'PayPal_Braintree::images/GooglePay_AcceptanceMark.png';

    /**
     * @var array
     */
    private array $icon = [];

    /**
     * @var array
     */
    private array $icons = [];

    /**
     * ConfigProvider constructor.
     *
     * @param Config $config
     * @param BraintreeAdapter $adapter
     * @param Repository $assetRepo
     * @param BraintreeConfig $braintreeConfig
     * @param TaxHelper $taxHelper
     * @param CcConfig $ccConfig
     * @param AssetSource $assetSource
     * @param File $fileIo
     */
    public function __construct(
        private readonly Config $config,
        private readonly BraintreeAdapter $adapter,
        private readonly Repository $assetRepo,
        private readonly BraintreeConfig $braintreeConfig,
        private readonly TaxHelper $taxHelper,
        private readonly CcConfig $ccConfig,
        private readonly AssetSource $assetSource,
        private readonly File $fileIo
    ) {
    }

    /**
     * @inheritDoc
     *
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
                    'environment' => $this->config->getEnvironment(),
                    'clientToken' => $this->getClientToken(),
                    'merchantId' => $this->config->getMerchantId(),
                    'cardTypes' => $this->config->getAvailableCardTypes(),
                    'btnColor' => $this->config->getBtnColor(),
                    'paymentMarkSrc' => $this->getPaymentMarkSrc(),
                    'vaultCode' => self::METHOD_VAULT_CODE,
                    'skipOrderReviewStep' => $this->config->skipOrderReviewStep(),
                    'priceIncludesTax' => $this->taxHelper->priceIncludesTax(),
                    'icons' => $this->getIcons(),
                    'multiCouponLimit' => $this->braintreeConfig->getMultiCouponLimit()
                ]
            ]
        ];
    }

    /**
     * Generate a new client token if necessary
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): string
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
        return $this->assetRepo->getUrl($this->fileId);
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
            $this->fileId,
            ['_secure' => true]
        );

        $this->icon = [
            'url' => $asset->getUrl(),
            'width' => 47,
            'height' => 25,
            'title' => __('Google Pay'),
        ];

        return $this->icon;
    }

    /**
     * Is Google Pay enabled for top of checkout
     *
     * @return bool
     */
    public function isActiveShipping(): bool
    {
        return (bool) $this->config->isActive() && $this->config->displayGooglePayButtonOnShipping();
    }

    /**
     * Get icons for the various card networks
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
}

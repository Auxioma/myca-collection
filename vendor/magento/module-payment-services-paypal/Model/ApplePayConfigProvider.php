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

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;

class ApplePayConfigProvider implements ConfigProviderInterface
{
    public const CODE = Config::PAYMENTS_SERVICES_PREFIX . 'apple_pay';
    public const PAYMENT_SOURCE = 'applepay';

    /**
     * @param Config $config
     * @param UrlInterface $url
     * @param BaseConfig $baseConfig
     * @param ConfigProvider $configProvider
     * @param PaymentsSDKConfigProvider $paymentSdkConfigProvider
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $url,
        private readonly BaseConfig $baseConfig,
        private readonly ConfigProvider $configProvider,
        private readonly PaymentsSDKConfigProvider $paymentSdkConfigProvider,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        $config = $this->configProvider->getConfig();
        $apsEnabled = $this->baseConfig->isConfigured();
        $config['payment'][self::CODE] = $this->getPaymentConfig($apsEnabled);
        $config['payment'][self::CODE]['express'] = $this->getExpressPaymentConfig($apsEnabled);
        return $config;
    }

    /**
     * Gets the configuration required for making a payment with Apple Pay on the regular checkout page.
     *
     * @param bool $apsEnabled
     * @return array
     * @throws NoSuchEntityException
     */
    private function getPaymentConfig(bool $apsEnabled): array
    {
        if ($apsEnabled && $this->config->isApplePayLocationEnabled(strtolower(Config::CHECKOUT_CHECKOUT_LOCATION))) {
            return [
                'isVisible' => true,
                'paymentTypeIconUrl' => $this->config->getViewFileUrl(
                    'Magento_PaymentServicesPaypal::images/applepay.png'
                ),
                'paymentSdkParams' => $this->paymentSdkConfigProvider->getPaymentsSDKParams(),
            ];
        } else {
            return [
                'isVisible' => false,
            ];
        }
    }

    /**
     * Gets the configuration required for making an express payment.
     *
     * @param bool $apsEnabled
     * @return array
     * @throws NoSuchEntityException
     */
    private function getExpressPaymentConfig(bool $apsEnabled): array
    {
        if ($apsEnabled && $this->config->isApplePayLocationEnabled(strtolower(Config::START_OF_CHECKOUT_LOCATION))) {
            return [
                'isVisible' => true,
                'placeOrderUrl' => $this->url->getUrl('paymentservicespaypal/smartbuttons_sdkbased/placeorder'),
                'paymentSdkParams' => $this->paymentSdkConfigProvider->getPaymentsSDKParams(),
                'sort' => $this->config->getSortOrder(self::CODE),
            ];
        } else {
            return [
                'isVisible' => false
            ];
        }
    }
}

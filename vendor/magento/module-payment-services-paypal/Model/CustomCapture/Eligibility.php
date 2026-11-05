<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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

namespace Magento\PaymentServicesPaypal\Model\CustomCapture;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\PaymentServicesPaypal\Model\ApplePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\Config as PaypalConfig;
use Magento\PaymentServicesPaypal\Model\FastlaneConfigProvider;
use Magento\PaymentServicesPaypal\Model\GooglePayConfigProvider;
use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\PaymentServicesPaypal\Model\SmartButtonsConfigProvider;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;

class Eligibility
{
    public const CONFIG_PATH_OVER_CAPTURE_ENABLED = 'payment/payment_services/over_capture_enabled';

    private const ELIGIBLE_METHODS = [
        HostedFieldsConfigProvider::CODE,
        HostedFieldsConfigProvider::CC_VAULT_CODE,
        SmartButtonsConfigProvider::CODE,
        ApplePayConfigProvider::CODE,
        GooglePayConfigProvider::CODE,
        FastlaneConfigProvider::CODE,
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param BaseConfig $baseConfig
     * @param PaypalConfig $paypalConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly BaseConfig $baseConfig,
        private readonly PaypalConfig $paypalConfig
    ) {
    }

    /**
     * Check if over capture config is enabled for a store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isOverCaptureEnabled(?int $storeId = null): bool
    {
        return $this->baseConfig->isEnabled($storeId)
            && (bool) $this->scopeConfig->getValue(
                self::CONFIG_PATH_OVER_CAPTURE_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    /**
     * Check if the order payment method can use over capture.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isEligibleOrder(OrderInterface $order): bool
    {
        $storeId = $order->getStoreId() === null ? null : (int) $order->getStoreId();
        if (!$this->isOverCaptureEnabled($storeId)) {
            return false;
        }

        $payment = $order->getPayment();
        if ($payment === null) {
            return false;
        }

        $method = (string) $payment->getMethod();
        if (!in_array($method, self::ELIGIBLE_METHODS, true)) {
            return false;
        }

        return $this->getPaymentAction($method, $storeId) === MethodInterface::ACTION_AUTHORIZE;
    }

    /**
     * Resolve payment action for a method. Vault uses Hosted Fields configuration.
     *
     * @param string $method
     * @param int|null $storeId
     * @return string
     */
    private function getPaymentAction(string $method, ?int $storeId = null): string
    {
        if ($method === HostedFieldsConfigProvider::CC_VAULT_CODE) {
            $method = HostedFieldsConfigProvider::CODE;
        }

        try {
            return $this->paypalConfig->getPaymentIntent($method, $storeId);
        } catch (NoSuchEntityException $exception) {
            return '';
        }
    }
}

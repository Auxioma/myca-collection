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

namespace PayPal\Braintree\Block\LayoutProcessor\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\UiConfigResolverInterface;
use PayPal\Braintree\Model\ApplePay\Ui\ConfigProvider as ApplePayConfigProvider;
use PayPal\Braintree\Model\GooglePay\Ui\ConfigProvider as GooglePayConfigProvider;
use PayPal\Braintree\Model\Ui\PayPal\ConfigProvider;

/**
 * Provides reCaptcha component configuration.
 */
class Onepage implements LayoutProcessorInterface
{
    /**
     * @param UiConfigResolverInterface $captchaUiConfigResolver
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param ConfigProvider $payPalConfigProvider
     * @param ApplePayConfigProvider $applePayConfigProvider
     * @param GooglePayConfigProvider $googlePayConfigProvider
     */
    public function __construct(
        private readonly UiConfigResolverInterface $captchaUiConfigResolver,
        private readonly IsCaptchaEnabledInterface $isCaptchaEnabled,
        private readonly ConfigProvider $payPalConfigProvider,
        private readonly ApplePayConfigProvider $applePayConfigProvider,
        private readonly GooglePayConfigProvider $googlePayConfigProvider
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $jsLayout
     * @return array
     * @throws InputException
     */
    public function process($jsLayout): array
    {
        // Enable/Disable Captcha.
        $jsLayout = $this->toggleCaptcha($jsLayout);

        // Enable/Disable Express Payment methods.
        return $this->toggleExpressPayments($jsLayout);
    }

    /**
     * Toggle ReCaptcha
     *
     * @param array $jsLayout
     * @return array
     * @throws InputException
     */
    private function toggleCaptcha(array $jsLayout): array
    {
        $key = 'braintree';

        if ($this->isCaptchaEnabled->isCaptchaEnabledFor($key)) {
            $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
            ['payment']['children']['payments-list']['children']['braintree-recaptcha-container']['children']
            ['braintree-recaptcha']['settings'] = $this->captchaUiConfigResolver->get($key);

            return $jsLayout;
        }

        if (isset($jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
            ['payment']['children']['payments-list']['children']['braintree-recaptcha-container']['children']
            ['braintree-recaptcha'])
        ) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
                ['payment']['children']['payments-list']['children']['braintree-recaptcha-container']['children']
                ['braintree-recaptcha']);
        }

        return $jsLayout;
    }

    /**
     * Remove express payment methods if disabled in the config.
     *
     * @param array $jsLayout
     * @return array
     */
    private function toggleExpressPayments(array $jsLayout): array
    {
        // If any of this express option is enabled then show the express section
        if ($this->payPalConfigProvider->isActiveShipping()
            || $this->applePayConfigProvider->isActiveShipping()
            || $this->googlePayConfigProvider->isActiveShipping()) {
            return $jsLayout;
        }

        // otherwise remove or unset the layout
        if (isset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
            ['shippingAddress']['children']['braintree-express-payments'])
        ) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
                ['shippingAddress']['children']['braintree-express-payments']);
        }

        return $jsLayout;
    }
}

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

namespace Magento\PaymentServicesPaypal\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ScopeOnboarding extends Field
{
    private const SCOPE_ONBOARDING_ADMIN_ROUTE = 'paymentservicespaypal/onboarding/scope';
    private const GET_ONBOARDING_STATUS_ADMIN_ROUTE = 'paymentservicespaypal/onboarding/status';
    private const ONBOARDING_COUNTRY_OPTIONS_ADMIN_ROUTE = 'paymentservicespaypal/onboarding/countryOptions';

    /**
     * @param PaypalMerchantResolver $paypalMerchantResolver
     * @param LoggerInterface $logger
     * @param PaymentServicesConfig $config
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        private readonly PaypalMerchantResolver $paypalMerchantResolver,
        private readonly LoggerInterface $logger,
        private readonly PaymentServicesConfig $config,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setTemplate('Magento_PaymentServicesPaypal::system/config/scope_onboarding.phtml');
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        if (!$this->shouldRenderButton($element)) {
            return '';
        }

        $element = clone $element;
        $element->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $websiteId = (int) $element->getScopeId();

        $this->addData([
            'scope_onboarding_url' => $this->getScopeOnboardingUrl($websiteId),
            'get_onboarding_status_url' => $this->getOnboardingStatusUrl($websiteId),
            'onboarding_country_options_url' => $this->getOnboardingCountryOptionsUrl($websiteId)
        ]);

        return $this->_toHtml();
    }

    /**
     * Determine whether the onboarding button should render.
     *
     * @param AbstractElement $element
     * @return bool
     */
    private function shouldRenderButton(AbstractElement $element): bool
    {
        if (!$this->isWebsiteScope($element)) {
            return false;
        }

        $websiteId = (int) $element->getScopeId();

        if ($websiteId <= 0 || !$this->isMBAScopingAtWebsiteLevel()) {
            return false;
        }

        $environment = $this->config->getEnvironmentTypeForWebsite($websiteId);

        if (!$this->isGlobalPaymentServicesMerchantIdConfigured($environment)) {
            return false;
        }

        try {
            return !$this->isWebsitePayPalMerchantActive($websiteId, $environment);
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Unable to determine PayPal scope onboarding button visibility.',
                [
                    'website_id' => $websiteId,
                    'environment' => $environment,
                    'message' => $exception->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Check whether the form element is rendered for website scope.
     *
     * @param AbstractElement $element
     * @return bool
     */
    private function isWebsiteScope(AbstractElement $element): bool
    {
        return $element->getScope() === ScopeInterface::SCOPE_WEBSITES;
    }

    /**
     * Check whether Payment Services MBA scoping is configured at website level.
     *
     * @return bool
     */
    private function isMBAScopingAtWebsiteLevel(): bool
    {
        return $this->config->getMultiBusinessAccountScopingLevel() === ScopeInterface::SCOPE_WEBSITE;
    }

    /**
     * Check whether the Payment Services merchant ID is configured for the selected environment.
     *
     * @param string $environment
     * @return bool
     */
    private function isGlobalPaymentServicesMerchantIdConfigured(string $environment): bool
    {
        return trim($this->config->getMerchantId($environment)) !== '';
    }

    /**
     * Check whether an active PayPal merchant exists for the exact website scope.
     *
     * @param int $websiteId
     * @param string $environment
     * @return bool
     */
    private function isWebsitePayPalMerchantActive(
        int $websiteId,
        string $environment
    ): bool {
        $websiteMerchant = $this->paypalMerchantResolver->getExactPayPalMerchant(
            ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
            $websiteId,
            $environment
        );

        return $websiteMerchant !== null
            && trim((string) $websiteMerchant->getId()) !== ''
            && $websiteMerchant->getStatus() === PaypalMerchantInterface::COMPLETED_STATUS;
    }

    /**
     * Build the URL used by the onboarding modal to load country and payment-experience options.
     *
     * @param int $websiteId
     * @return string
     */
    private function getOnboardingCountryOptionsUrl(int $websiteId): string
    {
        return $this->getUrl(self::ONBOARDING_COUNTRY_OPTIONS_ADMIN_ROUTE, [
            '_query' => [
                'websiteId' => $websiteId,
                'isAjax' => 'true'
            ]
        ]);
    }

    /**
     * Build scope onboarding admin URL.
     *
     * @param int $websiteId
     * @return string
     */
    private function getScopeOnboardingUrl(int $websiteId): string
    {
        return $this->getUrl(self::SCOPE_ONBOARDING_ADMIN_ROUTE, [
            '_query' => [
                'websiteId' => $websiteId
            ]
        ]);
    }

    /**
     * Build onboarding status admin URL.
     *
     * @param int $websiteId
     * @return string
     */
    private function getOnboardingStatusUrl(int $websiteId): string
    {
        return $this->getUrl(self::GET_ONBOARDING_STATUS_ADMIN_ROUTE, [
            '_query' => [
                'forceRefresh' => 'true',
                'websiteId' => $websiteId
            ]
        ]);
    }
}

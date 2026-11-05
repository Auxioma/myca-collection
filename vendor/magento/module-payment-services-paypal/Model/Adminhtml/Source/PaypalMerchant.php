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

namespace Magento\PaymentServicesPaypal\Model\Adminhtml\Source;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ScopeInterface as DefaultScopeInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Magento\Store\Model\ScopeInterface;

class PaypalMerchant extends Field
{
    private const MAP_SCOPE_FROM_ADMIN_TO_SAAS = [
        DefaultScopeInterface::SCOPE_DEFAULT => PaypalMerchantResolver::GLOBAL_SCOPE,
        ScopeInterface::SCOPE_WEBSITES => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
        ScopeInterface::SCOPE_STORES => ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE
    ];
    private const SANDBOX_PAYPAL_PROFILE_URL = 'https://sandbox.paypal.com/businessprofile/settings';
    private const PRODUCTION_PAYPAL_PROFILE_URL = 'https://www.paypal.com/businessprofile/settings';

    /**
     * @param PaypalMerchantResolver $paypalMerchantResolver
     * @param Config $config
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        private readonly PaypalMerchantResolver $paypalMerchantResolver,
        private readonly Config $config,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Preparing global layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setTemplate('Magento_PaymentServicesPaypal::system/config/paypal_merchant_id.phtml');
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $element = clone $element;
        $element->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Retrieve element HTML markup
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $scopeType = self::MAP_SCOPE_FROM_ADMIN_TO_SAAS[$element->getScope()] ?? PaypalMerchantResolver::GLOBAL_SCOPE;
        $scopeId = (int)$element->getScopeId() ?? 0;
        $environment = $this->paypalMerchantResolver->resolveEnvironment($scopeType, $scopeId);

        $paypalMerchant = $this->paypalMerchantResolver->getPayPalMerchant($scopeType, $scopeId);
        $statusInformation = $this->getStatusInformation($paypalMerchant->getStatus(), $environment);

        $this->addData(
            [
                'paypal_merchant_id' => $paypalMerchant->getId() ?? false,
                'paypal_merchant_message' => $statusInformation->getMessage(),
                'paypal_merchant_status' => $statusInformation->getStatus(),
                'paypal_merchant_status_style' =>  $statusInformation->getStyle(),
            ]
        );

        return $this->_toHtml();
    }

    /**
     * Get status information to display
     *
     * @param ?string $status
     * @param string $environment
     * @return PaypalMerchantStatusInformation
     */
    private function getStatusInformation(
        ?string $status,
        string $environment
    ) : PaypalMerchantStatusInformation {
        $payPalProfileLink = self::PRODUCTION_PAYPAL_PROFILE_URL;
        if ($environment === Config::SANDBOX_ENVIRONMENT) {
            $payPalProfileLink = self::SANDBOX_PAYPAL_PROFILE_URL;
        }

        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        return match ($status) {
            PaypalMerchantInterface::COMPLETED_STATUS => new PaypalMerchantStatusInformation(
                null,
                'notice',
                __('ACTIVE')
            ),
            PaypalMerchantInterface::UNVERIFIED_STATUS => new PaypalMerchantStatusInformation(
                __('Please confirm your email address on your <a href="%1" target="_blank">PayPal Profile Settings</a> in order to receive payments.', $payPalProfileLink),
                'major',
                __('ERROR')
            ),
            PaypalMerchantInterface::REVOKED_STATUS => new PaypalMerchantStatusInformation(
                __('There is an issue with your PayPal account. Please reach out to Adobe sales for assistance.'),
                'major',
                __('ERROR')
            ),
            default => new PaypalMerchantStatusInformation(
                null,
                'info',
                __('INACTIVE')
            ),
        };
    }
}

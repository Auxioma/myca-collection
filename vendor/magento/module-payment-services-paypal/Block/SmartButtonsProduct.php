<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2021 Adobe
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
namespace Magento\PaymentServicesPaypal\Block;

use Magento\Catalog\Helper\Data;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Downloadable\Model\Product\Type;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\PaymentServicesPaypal\Model\Config;

/**
 * @api
 */
class SmartButtonsProduct extends SmartButtons
{
    /**
     * @param Context $context
     * @param Config $config
     * @param Session $session
     * @param Data $catalogData
     * @param string $pageType
     * @param array $componentConfig
     * @param array $data
     * @param HttpContext|null $httpContext
     */
    public function __construct(
        Context $context,
        Config $config,
        Session $session,
        private readonly Data $catalogData,
        private readonly string $pageType = 'minicart',
        array $componentConfig = [],
        array $data = [],
        private ?HttpContext $httpContext = null
    ) {
        $this->httpContext = $httpContext ?: ObjectManager::getInstance()->get(HttpContext::class);
        parent::__construct(
            $context,
            $config,
            $session,
            $pageType,
            $componentConfig,
            $data
        );
    }

    /**
     * @inheritdoc
     */
    public function getComponentParams() : array
    {
        return array_merge(
            parent::getComponentParams(),
            [
                // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
                'createOrderUrl' => $this->getUrl(
                    'paymentservicespaypal/smartbuttons/createpaypalorder',
                    ['location' => $this->pageType]
                ),
                'cancelUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/cancel'),
                'addToCartUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/addtocart'),
                'isVirtual' => $this->catalogData->getProduct() !== null
                    && $this->catalogData->getProduct()->isVirtual()
            ]
        );
    }

    /**
     * @inheritdoc
     * @throws LocalizedException
     */
    public function getPaymentSDKBasedComponentParams() : array
    {
        return array_merge(
            parent::getPaymentSDKBasedComponentParams(),
            [
                'addToCartUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/addtocart'),
                'baseCurrencyCode' => $this->_storeManager->getStore()->getBaseCurrencyCode(),
                'currentCurrencyRate' => (float) $this->_storeManager->getStore()->getCurrentCurrencyRate(),
                'isVirtual' => $this->catalogData->getProduct() !== null
                    && $this->catalogData->getProduct()->isVirtual()
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function isLocationEnabled(string $location): bool
    {
        return parent::isLocationEnabled($location) && $this->canShowExpressButtons();
    }

    /**
     * @inheritdoc
     */
    public function isApplePayLocationEnabled(string $location): bool
    {
        return parent::isApplePayLocationEnabled($location) && $this->canShowExpressButtons();
    }

    /**
     * @inheritdoc
     */
    public function isGooglePayLocationEnabled(string $location): bool
    {
        return parent::isGooglePayLocationEnabled($location) && $this->canShowExpressButtons();
    }

    /**
     * Check whether express buttons can be displayed on the current product page.
     *
     * @return bool
     */
    private function canShowExpressButtons(): bool
    {
        $product = $this->catalogData->getProduct();
        if ($product === null) {
            return true;
        }
        if ($this->isCustomerLoggedIn()) {
            return true;
        }

        return $product->getTypeId() !== Type::TYPE_DOWNLOADABLE;
    }

    /**
     * Check whether customer logged in or not
     *
     * @return bool
     */
    private function isCustomerLoggedIn(): bool
    {
        return (bool) $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }
}

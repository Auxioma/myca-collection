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

use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\PaymentServicesPaypal\Model\PaymentsSDKConfigProvider;

/**
 * @api
 */
class SmartButtons extends Template implements ShortcutInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var array
     */
    private $componentConfig;

    /**
     * @var string
     */
    private $pageType;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CompositeConfigProvider
     */
    protected $configProvider;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var PaymentsSDKConfigProvider
     */
    private $paymentsSDKConfigProvider;

    /**
     * @param Context $context
     * @param Config $config
     * @param Session $session
     * @param string $pageType
     * @param array $componentConfig
     * @param array $data
     * @param Json|null $serializer
     * @param CompositeConfigProvider|null $compositeConfigProvider
     * @param CheckoutSession|null $checkoutSession
     * @param PaymentsSDKConfigProvider|null $paymentsSDKConfigProvider
     *
     * @SuppressWarnings(Magento.TypeDuplication)
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Config $config,
        Session $session,
        string $pageType = 'minicart',
        array $componentConfig = [],
        array $data = [],
        ?Json $serializer = null,
        ?CompositeConfigProvider $compositeConfigProvider = null,
        ?CheckoutSession $checkoutSession = null,
        ?PaymentsSDKConfigProvider $paymentsSDKConfigProvider = null
    ) {
        $this->config = $config;
        $this->componentConfig = $componentConfig;
        $this->pageType = $pageType;
        $this->session = $session;
        parent::__construct(
            $context,
            $data
        );
        /** @phpstan-ignore-next-line */
        $this->setTemplate($data['template'] ?? $componentConfig[$this->pageType]['template']);
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
        $this->configProvider = $compositeConfigProvider
            ?: ObjectManager::getInstance()->get(CompositeConfigProvider::class);
        $this->checkoutSession = $checkoutSession ?: ObjectManager::getInstance()->get(CheckoutSession::class);
        $this->paymentsSDKConfigProvider = $paymentsSDKConfigProvider ?:
            ObjectManager::getInstance()->get(PaymentsSDKConfigProvider::class);
    }

    /**
     * Get payment method alias
     *
     * @return string
     */
    public function getAlias() : string
    {
        return 'magpaypayments_smart_buttons';
    }

    /**
     * Get component params of payment methods
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getComponentParams() : array
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        return [
            'createOrderUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/createpaypalorder'),
            'syncQuoteUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/updatequote'),
            'orderReviewUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/review'),
            'cancelUrl' => $this->getUrl('checkout/cart'),
            'estimateShippingMethodsWhenLoggedInUrl' => $this->getUrl('rest/V1/carts/mine/estimate-shipping-methods'),
            'estimateShippingMethodsWhenGuestUrl' => $this->getUrl(
                'rest/V1/guest-carts/:cartId/estimate-shipping-methods'
            ),
            'shippingInformationWhenLoggedInUrl' => $this->getUrl('rest/V1/carts/mine/shipping-information'),
            'shippingInformationWhenGuestUrl' => $this->getUrl('rest/V1/guest-carts/:quoteId/shipping-information'),
            'updatePayPalOrderUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/updatepaypalorder/'),
            'countriesUrl' => $this->getUrl('rest/V1/directory/countries/:countryCode'),
            'setQuoteAsInactiveUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/setquoteasinactive'),
            'placeOrderUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/placeorder/'),
            'getOrderDetailsUrl' => $this->getUrl('paymentservicespaypal/order/getcurrentorder'),
            'threeDSMode' => $this->config->getGooglePayThreeDS() !== "0"
                ? $this->config->getGooglePayThreeDS()
                : false,
            'styles' => $this->getStyles(),
            'isVirtual' => $this->session->getQuote()->isVirtual(),
            'googlePayMode' => $this->config->getGooglePayMode(),
            'pageType' => $this->pageType,
            'completeOrderUrl' => $baseUrl . 'rest/V1/payment-order/completeOrder',
            'appSwitchWhenAvailable' => $this->config->getAppSwitch($storeId)
        ];
    }

    /**
     * Returns params for smart button UI components based on the Payments JS SDK.
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getPaymentSDKBasedComponentParams() : array
    {
        $params = [
            'pageType' => $this->pageType,
            'placeOrderUrl' => $this->getUrl('paymentservicespaypal/smartbuttons_sdkbased/placeorder'),
            'reviewPageUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/review'),
            'restoreQuoteUrl' => $this->getUrl('paymentservicespaypal/smartbuttons/restorequote'),
            'paymentSdkParams' => $this->paymentsSDKConfigProvider->getPaymentsSDKParams(),
            'cancelUrl' => $this->getUrl('checkout/cart')
        ];

        $location = $this->mapPageTypeToLocation($this->pageType);

        if ($this->isGooglePayLocationEnabled($location)) {
            $params['canSkipReviewForGooglePay'] = $this->config->getGooglePaySkipReview();
        }

        if ($this->isLocationEnabled($location)) {
            $params['syncQuoteUrl'] = $this->getUrl('paymentservicespaypal/smartbuttons/updatequote');
            $params['canSkipReviewForPayPal'] = $this->config->getPayPalSkipReview();
        }

        return $params;
    }

    /**
     * Get location by mapping pageType with location
     *
     * @param string $pageType
     * @return string
     */
    private function mapPageTypeToLocation(string $pageType) : string
    {
        $location = mb_strtoupper($pageType);

        // Map "product" to "product_detail" for consistency
        if ($location === Config::PRODUCT_CHECKOUT_LOCATION) {
            $location = Config::PRODUCT_DETAIL_CHECKOUT_LOCATION;
        }

        return mb_strtolower($location);
    }

    /**
     * Check if smart buttons enabled.
     *
     * @return bool
     */
    public function isEnabled() : bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Check if smart buttons for a particular location (e.g., minicart) is enabled
     *
     * @param string $location
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isLocationEnabled(string $location): bool
    {
        return $this->config->isLocationEnabled($location) && $this->isEnabled();
    }

    /**
     * Check if Apple Pay for a particular location (e.g., minicart) is enabled
     *
     * @param string $location
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isApplePayLocationEnabled(string $location): bool
    {
        return $this->config->isApplePayLocationEnabled($location) && $this->isEnabled();
    }

    /**
     * Check if Google Pay for a particular location (e.g., minicart) is enabled
     *
     * @param string $location
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isGooglePayLocationEnabled(string $location): bool
    {
        return $this->config->isGooglePayLocationEnabled($location) && $this->isEnabled();
    }

    /**
     * Get styles of Smart Buttons
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getStyles() : array
    {
        return array_merge($this->config->getButtonConfiguration(), $this->config->getGooglePayStyles());
    }

    /**
     * Get Serialized Checkout Config
     *
     * @return bool|string
     *
     * @deprecated This method is no longer used.
     * @see No replacement.
     */
    public function getSerializedCheckoutConfig()
    {
        return $this->serializer->serialize($this->configProvider->getConfig());
    }

    /**
     * Check if quote exists
     *
     * @return bool
     * @throws LocalizedException
     *
     * @deprecated This method is no longer used.
     * @see No replacement.
     */
    public function doesQuoteExist(): bool
    {
        try {
            return $this->checkoutSession->getQuote()->getId() != null;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}

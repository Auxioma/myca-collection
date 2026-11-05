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

namespace PayPal\Braintree\Block\GooglePay;

use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use PayPal\Braintree\Model\GooglePay\Auth;
use PayPal\Braintree\Gateway\Config\Config as BraintreeConfig;
use PayPal\Braintree\Gateway\Config\GooglePay\Config as GooglePayConfig;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractButton extends Template
{
    /**
     * Button constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param Auth $auth
     * @param GooglePayConfig $googlePayConfig
     * @param TaxHelper $taxHelper
     * @param FormatInterface $localeFormat
     * @param array $data
     * @param Data|null $catalogHelper
     * @param CustomerSession|null $customerSession
     * @param QuoteIdToMaskedQuoteIdInterface|null $maskedQuoteId
     * @param BraintreeConfig|null $braintreeConfig
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        protected readonly Session $checkoutSession,
        protected readonly MethodInterface $payment,
        protected readonly Auth $auth,
        protected readonly GooglePayConfig $googlePayConfig,
        protected readonly TaxHelper $taxHelper,
        protected readonly FormatInterface $localeFormat,
        array $data = [],
        protected ?Data $catalogHelper = null,
        protected ?CustomerSession $customerSession = null,
        protected ?QuoteIdToMaskedQuoteIdInterface $maskedQuoteId = null,
        protected ?BraintreeConfig $braintreeConfig = null,
    ) {
        parent::__construct($context, $data);

        $this->catalogHelper = $catalogHelper ?: ObjectManager::getInstance()->get(Data::class);
        $this->customerSession = $customerSession ?: ObjectManager::getInstance()->get(CustomerSession::class);
        $this->maskedQuoteId = $maskedQuoteId ?: ObjectManager::getInstance()->get(
            QuoteIdToMaskedQuoteIdInterface::class
        );
        $this->braintreeConfig = $braintreeConfig ?: ObjectManager::getInstance()->get(BraintreeConfig::class);
        $this->setData('sort_order', $googlePayConfig->getSortOrder());
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string // @codingStandardsIgnoreLine
    {
        if ($this->isActive()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * Is method active
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function isActive(): bool
    {
        return $this->payment->isAvailable($this->checkoutSession->getQuote());
    }

    /**
     * Merchant name to display in popup
     *
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->auth->getMerchantId();
    }

    /**
     * Get environment code
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getEnvironment(): string
    {
        return $this->auth->getEnvironment();
    }

    /**
     * Braintree API token
     *
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): string
    {
        return $this->auth->getClientToken();
    }

    /**
     * URL To success page
     *
     * @return string
     */
    public function getActionSuccess(): string
    {
        return $this->skipOrderReviewStep()
            ? $this->getUrl('checkout/onepage/success', ['_secure' => true])
            : $this->getUrl('braintree/googlepay/review', ['_secure' => true]);
    }

    /**
     * Currency code
     *
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode(): ?string
    {
        if ($this->checkoutSession->getQuote()->getCurrency()) {
            return $this->checkoutSession->getQuote()->getCurrency()->getBaseCurrencyCode();
        }

        return null;
    }

    /**
     * Cart grand total
     *
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAmount(): float
    {
        return (float) $this->checkoutSession->getQuote()->getBaseGrandTotal();
    }

    /**
     * Available card types
     *
     * @return array
     */
    public function getAvailableCardTypes(): array
    {
        return $this->auth->getAvailableCardTypes();
    }

    /**
     * BTN Color
     *
     * @return int
     */
    public function getBtnColor(): int
    {
        return $this->auth->getBtnColor();
    }

    /**
     * Get an array of the 3DSecure specific data
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get3DSecureConfigData(): array
    {
        if (!$this->auth->is3DSecureEnabled()) {
            return [
                'enabled' => false,
                'challengeRequested' => false,
                'thresholdAmount' => 0.0,
                'specificCountries' => [],
                'ipAddress' => ''
            ];
        }

        return [
            'enabled' => true,
            'challengeRequested' => $this->auth->is3DSecureAlwaysRequested(),
            'thresholdAmount' => $this->auth->get3DSecureThresholdAmount(),
            'specificCountries' => $this->auth->get3DSecureSpecificCountries(),
            'ipAddress' => $this->auth->getIpAddress()
        ];
    }

    /**
     * Get Store Code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode(): string
    {
        return $this->_storeManager->getStore()->getCode();
    }

    /**
     * Get Quote ID
     *
     * @return int|string
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): int|string
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->checkoutSession->getQuoteId() ?? 0;
        }
        return $this->checkoutSession->getQuoteId()
            ? $this->maskedQuoteId->execute((int) $this->checkoutSession->getQuoteId())
            : 0;
    }

    /**
     * Can skip order review step
     *
     * @return bool
     */
    public function skipOrderReviewStep(): bool
    {
        return (bool) $this->googlePayConfig->skipOrderReviewStep();
    }

    /**
     * Get price format
     *
     * @return array
     */
    public function getPriceFormat(): array
    {
        return $this->localeFormat->getPriceFormat();
    }

    /**
     * Check if product prices includes tax.
     *
     * @return bool
     */
    public function priceIncludesTax(): bool
    {
        return $this->taxHelper->priceIncludesTax();
    }

    /**
     * Get coupon limits for each cart.
     *
     * @return string
     */
    public function getMultiCouponLimit(): string
    {
        return $this->braintreeConfig->getMultiCouponLimit();
    }

    /**
     * Get product
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        return $this->catalogHelper->getProduct();
    }
}

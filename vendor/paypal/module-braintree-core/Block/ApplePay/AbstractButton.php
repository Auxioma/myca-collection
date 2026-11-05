<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\ApplePay;

use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use PayPal\Braintree\Api\Data\AuthDataInterface;
use PayPal\Braintree\Gateway\Config\ApplePay\Config;
use PayPal\Braintree\Model\ApplePay\Auth;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractButton extends Template
{
    /**
     * @var AuthDataInterface|Auth
     */
    protected AuthDataInterface|Auth $auth;

    /**
     * AbstractButton constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param Auth $auth
     * @param array $data
     * @param Config|null $applePayConfig
     * @param TaxHelper|null $taxHelper
     * @param Data|null $catalogHelper
     * @param CustomerSession|null $customerSession
     * @param QuoteIdToMaskedQuoteIdInterface|null $maskedQuoteId
     * @throws InputException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        protected readonly Session $checkoutSession,
        protected readonly MethodInterface $payment,
        Auth $auth,
        array $data = [],
        protected ?Config $applePayConfig = null,
        protected ?TaxHelper $taxHelper = null,
        protected ?Data $catalogHelper = null,
        protected ?CustomerSession $customerSession = null,
        protected ?QuoteIdToMaskedQuoteIdInterface $maskedQuoteId = null
    ) {
        parent::__construct($context, $data);

        $this->auth = $auth->get();
        $this->applePayConfig = $applePayConfig ?: ObjectManager::getInstance()->get(Config::class);
        $this->taxHelper = $taxHelper ?: ObjectManager::getInstance()->get(TaxHelper::class);
        $this->catalogHelper = $catalogHelper ?: ObjectManager::getInstance()->get(Data::class);
        $this->customerSession = $customerSession ?: ObjectManager::getInstance()->get(CustomerSession::class);
        $this->maskedQuoteId = $maskedQuoteId ?: ObjectManager::getInstance()->get(
            QuoteIdToMaskedQuoteIdInterface::class
        );
        $this->setData('sort_order', $auth->getSortOrder());
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
     * Check if payment method is available
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
    public function getMerchantName(): string
    {
        return $this->auth->getDisplayName();
    }

    /**
     * Braintree API token
     *
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientToken(): ?string
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
        return $this->auth->getActionSuccess();
    }

    /**
     * Is customer logged in flag
     *
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return $this->auth->isLoggedIn();
    }

    /**
     * Get currency code
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
     * Get store code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode(): string
    {
        return $this->auth->getStoreCode();
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
     * Check if product prices includes tax.
     *
     * @return bool
     */
    public function priceIncludesTax(): bool
    {
        return $this->taxHelper->priceIncludesTax();
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

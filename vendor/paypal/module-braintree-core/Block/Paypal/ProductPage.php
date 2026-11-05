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

namespace PayPal\Braintree\Block\Paypal;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use PayPal\Braintree\Gateway\Config\PayPal\Config;

/**
 * @api
 * @since 100.0.2
 */
class ProductPage extends Button
{
    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return !$this->isProductVirtual()
            && $this->config->isActive()
            && $this->config->isProductPageButtonEnabled()
            && ($this->getAmount() > 0);
    }

    /**
     * Get Currency code
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCurrency(): string
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get currency symbol
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCurrencySymbol(): string
    {
        return $this->currency->load($this->getCurrency())->getCurrencySymbol();
    }

    /**
     * Get product final amount
     *
     * @return float
     * @throws NoSuchEntityException
     */
    public function getAmount(): float
    {
        $product = $this->getProduct();

        // Get store and conversion rate
        $store = $this->_storeManager->getStore();
        $currentCurrency = $store->getCurrentCurrencyCode();
        $rate = $store->getBaseCurrency()->getRate($currentCurrency) ?: 1;

        // Helper to convert to base currency
        $convertToBase = function (float $price) use ($rate) {
            return round(($price / $rate), 2); // Convert store currency back to base
        };

        // Configurable product
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $price = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
            return (float) $convertToBase($price);
        }

        // Grouped product
        if ($product->getTypeId() === Grouped::TYPE_CODE) {
            $groupedProducts = $product->getTypeInstance()->getAssociatedProducts($product);
            if (!empty($groupedProducts)) {
                $price = $groupedProducts[0]->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                return (float) $convertToBase($price);
            }
        }

        $price = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        return (float) $convertToBase($price);
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return 'braintree.paypal.product';
    }

    /**
     * Get container Id
     *
     * @return string
     */
    public function getContainerId(): string
    {
        return 'braintree-paypal-product';
    }

    /**
     * Get location
     *
     * @return string
     */
    public function getLocation(): string
    {
        return 'productpage';
    }

    /**
     * Get action success url
     *
     * @return string
     */
    public function getActionSuccess(): string
    {
        return $this->skipOrderReviewStep()
            ? $this->getUrl('checkout/onepage/success', ['_secure' => true])
            : $this->getUrl('braintree/paypal/oneclick', ['_secure' => true]);
    }

    /**
     * Get button shape
     *
     * @param string $type
     * @return string
     */
    public function getButtonShape(string $type): string
    {
        return $this->config->getButtonShape(Config::BUTTON_AREA_PDP, $type);
    }

    /**
     * Get button color
     *
     * @param string $type
     * @return string
     */
    public function getButtonColor(string $type): string
    {
        if ($type === 'credit') {
            return $this->config->getCreditButtonColor(Config::BUTTON_AREA_PDP);
        }
        return $this->config->getButtonColor(Config::BUTTON_AREA_PDP, $type);
    }

    /**
     * Get button label
     *
     * @param string $type
     * @return string
     */
    public function getButtonLabel(string $type): string
    {
        return $this->config->getButtonLabel(Config::BUTTON_AREA_PDP, $type);
    }

    /**
     * @inheritDoc
     */
    public function getDisabledFunding(): array
    {
        return [
            'card' => $this->config->isFundingOptionCardDisabled(Config::KEY_PAYPAL_DISABLED_FUNDING_PDP),
            'elv' => $this->config->isFundingOptionElvDisabled(Config::KEY_PAYPAL_DISABLED_FUNDING_PDP)
        ];
    }

    /**
     * Get button config
     *
     * @return array
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getButtonConfig(): array
    {
        return [
            'clientToken' => $this->getClientToken(),
            'currency' => $this->getCurrency(),
            'environment' => $this->getEnvironment(),
            'merchantCountry' => $this->getMerchantCountry(),
            'isCreditActive' => $this->isCreditActive(),
            'skipOrderReviewStep' => $this->skipOrderReviewStep(),
            'pageType' => 'product-details',
            'locale' => $this->getLocale(),
            'canSendCartLineItems' => $this->config->canSendCartLineItemsForPayPal(),
            'contactPreference' => $this->config->getContactPreference(),
            'isProductVirtual' => $this->isProductVirtual(),
        ];
    }

    /**
     * Get button styling
     *
     * @return array
     */
    public function getMessageStyles(): array
    {
        return $this->config->getMessageStyles(Config::BUTTON_AREA_PDP);
    }

    /**
     * Check whether product is virtual or configurable contains virtual product or not
     *
     * @return bool
     */
    private function isProductVirtual(): bool
    {
        $product = $this->getProduct();
        $isVirtual = $product->isVirtual();
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            foreach ($product->getTypeInstance()->getUsedProducts($product) as $simple) {
                if ($simple->isVirtual()) {
                    $isVirtual = true;
                    break;
                }
            }
        }

        return $isVirtual;
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

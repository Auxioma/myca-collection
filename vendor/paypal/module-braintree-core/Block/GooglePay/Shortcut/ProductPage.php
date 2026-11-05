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

namespace PayPal\Braintree\Block\GooglePay\Shortcut;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

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
        return $this->googlePayConfig->isActive()
            && $this->googlePayConfig->displayGooglePayButtonOnPdp()
            && ($this->getAmount() > 0);
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return 'braintree.googlepay.product';
    }

    /**
     * Get container id
     *
     * @return string
     */
    public function getContainerId(): string
    {
        return 'braintree-google-pay-product';
    }

    /**
     * Get product final amount
     *
     * @return float
     * @throws NoSuchEntityException
     */
    public function getAmount(): float
    {
        /** @var Product $product */
        $product = $this->catalogHelper->getProduct();

        // Get store and conversion rate
        $store = $this->_storeManager->getStore();
        $currentCurrency = $store->getCurrentCurrencyCode();
        $rate = $store->getBaseCurrency()->getRate($currentCurrency) ?: 1;

        // Helper to convert to base currency
        $convertToBase = function (float $price) use ($rate) {
            return round(($price / $rate), 2); // Convert store currency back to base
        };

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $price = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
            return (float) $convertToBase($price);
        }

        if ($product->getTypeId() === Grouped::TYPE_CODE) {
            $groupedProducts = $product->getTypeInstance()->getAssociatedProducts($product);
            if (!empty($groupedProducts)) {
                $price = $groupedProducts[0]->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                return (float) $convertToBase($price);
            }
        }

        $price = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        return (float) $convertToBase($price);
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
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Check whether product or configurable contains virtual product or not
     *
     * @return bool
     */
    public function isProductVirtual(): bool
    {
        $product = $this->catalogHelper->getProduct();
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
}

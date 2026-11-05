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

namespace PayPal\Braintree\Model;

use Exception;
use PayPal\Braintree\Api\PayPalOneClickInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Braintree\Model\Request\Data\Helper\MaskedQuoteIdHelper;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayPalOneClick implements PayPalOneClickInterface
{
    /**
     * @param SerializerInterface $serializer
     * @param QuoteFactory $quoteFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param CartRepositoryInterface $cartRepository
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param MaskedQuoteIdHelper $maskedQuoteIdHelper
     * @param GetLineItemsAndAmountBreakDown $getLineItemsAndAmountBreakDown
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly QuoteFactory $quoteFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Session $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly MaskedQuoteIdHelper $maskedQuoteIdHelper,
        private readonly GetLineItemsAndAmountBreakDown $getLineItemsAndAmountBreakDown,
    ) {
    }

    /**
     * Rest endpoint to add product to cart order (mainly use for PayPal button from PDP)
     *
     * @param string $method
     * @param string $productData
     * @return string
     * @throws LocalizedException
     */
    public function execute(string $method, string $productData): string
    {
        $originalQuote = $this->checkoutSession->getQuote();
        $newQuote = $this->quoteFactory->create();
        $newQuote->setStoreId($this->storeManager->getStore()->getId());
        if ($this->customerSession->isLoggedIn()) {
            $newQuote->setCustomerId($this->customerSession->getCustomerId());
            $newQuote->setCustomerEmail($this->customerSession->getCustomerData()->getEmail());
            $newQuote->setCustomerGroupId($this->customerSession->getCustomerGroupId());
            $newQuote->setCustomerFirstname($this->customerSession->getCustomerData()->getFirstname());
            $newQuote->setCustomerLastname($this->customerSession->getCustomerData()->getLastname());
        } else {
            $newQuote->setCustomerIsGuest(1);
        }
        try {
            $productData = $this->transformFormData($productData);
            $product = $this->productRepository->getById(
                $productData['product'],
                false,
                $this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__("Cannot create PayPal order. Message: " . $exception->getMessage()));
        }

        $newQuote->setInventoryProcessed(false);
        try {
            $newQuote->addProduct($product, $productData);
        } catch (Exception $exception) {
            throw new LocalizedException(__("Cannot create PayPal order. Message: " . $exception->getMessage()));
        }
        $this->cartRepository->save($newQuote);
        $newQuote = $this->cartRepository->getActive($newQuote->getId());
        $newQuote->collectTotals();
        $newQuote->setTotalsCollectedFlag(false);
        $this->checkoutSession->replaceQuote($newQuote);
        if ($originalQuote && $originalQuote->getId()) {
            $originalQuote->setIsActive(false);
            $this->cartRepository->save($originalQuote);
        }

        $cartId = $this->maskedQuoteIdHelper->fetchAndEnsureQuoteMaskIdExist((int) $newQuote->getId());

        return $this->getLineItemsAndAmountBreakDown->execute($method, $cartId);
    }

    /**
     * Transform data for product
     *
     * @param string $formData
     * @return DataObject
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function transformFormData(string $formData): DataObject
    {
        $formData = $this->serializer->unserialize($formData);
        $newData = new DataObject();
        $superAttributes = [];
        $superGroup = [];
        $bundleOptions = [];
        $bundleOptionsQty = [];
        foreach ($formData as $item) {
            if (str_contains($item['name'], 'super_attribute')) {
                $superAttributes[(int) filter_var($item['name'], FILTER_SANITIZE_NUMBER_INT)] = $item['value'];
            }
            if (str_contains($item['name'], 'super_group')) {
                $superGroup[(int) filter_var($item['name'], FILTER_SANITIZE_NUMBER_INT)] = $item['value'];
            }
            if (str_contains($item['name'], 'bundle_option_qty')) {
                $bundleOptionsQty[(int) filter_var($item['name'], FILTER_SANITIZE_NUMBER_INT)] = $item['value'];
                continue;
            }
            if (str_contains($item['name'], 'bundle_option')) {
                $bundleOptions[(int) filter_var($item['name'], FILTER_SANITIZE_NUMBER_INT)] = $item['value'];
                continue;
            }
            $newData[$item['name']] = $item['value'];
        }

        // For Configurable Product
        if (!empty($superAttributes)) {
            $newData->setData('super_attribute', $superAttributes);
        }

        // For Grouped Product
        if (!empty($superGroup)) {
            $newData->setData('super_group', $superGroup);
        }

        // For Bundle Product
        if (!empty($bundleOptions) && !empty($bundleOptionsQty)) {
            $newData->setData('bundle_option', $bundleOptions);
            $newData->setData('bundle_option_qty', $bundleOptionsQty);
        }

        return $newData;
    }
}

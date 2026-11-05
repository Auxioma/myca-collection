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

namespace Magento\PaymentServicesPaypal\Model\ShippingCallback;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\Config as PaypalConfig;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Tax\Model\Config as TaxConfig;
use Psr\Log\LoggerInterface;

class ResponseBuilder
{
    private const MAX_SHIPPING_OPTIONS = 10;

    /**
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param OrderHelper $orderHelper
     * @param ShippingProcessor $shippingProcessor
     * @param MerchantService $merchantService
     * @param LoggerInterface $logger
     * @param TaxConfig $taxConfig
     * @param PaypalConfig $paypalConfig
     */
    public function __construct(
        private readonly ScopeHeadersBuilder $scopeHeaderBuilder,
        private readonly OrderHelper $orderHelper,
        private readonly ShippingProcessor $shippingProcessor,
        private readonly MerchantService $merchantService,
        private readonly LoggerInterface $logger,
        private readonly TaxConfig $taxConfig,
        private readonly PaypalConfig $paypalConfig
    ) {
    }

    /**
     * Build merchant response
     *
     * @param CartInterface|Quote $quote
     * @param array $requestData
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function buildResponse(CartInterface|Quote $quote, array $requestData): array
    {
        try {
            $shippingMethods = $this->shippingProcessor->getShippingMethods($quote);
            $shippingMethods = $this->limitShippingMethods($shippingMethods);
            $shippingMethods = $this->shippingProcessor->setDefaultShippingMethod($quote, $shippingMethods);
            $shippingMethods = $this->collectShippingOptionAmounts($quote, $shippingMethods);
            $shippingMethods = $this->formatShippingOptionAmounts($shippingMethods);

            $quote->getPayment()->setAdditionalInformation('paypal_order_amount', $quote->getBaseGrandTotal());
            $quote->save();
            $payPalMerchantId = $this->getPayPalMerchantId($quote);
        } catch (Exception $e) {
            $this->logger->error($e);
            throw new LocalizedException(__($e->getMessage()));
        }

        return [
            'id' => $payPalMerchantId,
            'purchase_units' => [
                [
                    'reference_id' => $requestData['purchase_units'][0]['reference_id'],
                    'amount' => $this->getAmountData($quote),
                    'items' => $this->orderHelper->getLineItems($quote, $quote->getReservedOrderId()),
                    'shipping_options' => $shippingMethods
                ]
            ]
        ];
    }

    /**
     * Limit shipping methods to the number supported by PayPal
     *
     * If the selected method is outside the limit, return the first nine methods followed by the selected method.
     *
     * @param array $shippingMethods
     * @return array
     */
    private function limitShippingMethods(array $shippingMethods): array
    {
        if (count($shippingMethods) <= self::MAX_SHIPPING_OPTIONS) {
            return array_values($shippingMethods);
        }

        $limitedMethods = array_slice($shippingMethods, 0, self::MAX_SHIPPING_OPTIONS);
        foreach ($limitedMethods as $shippingMethod) {
            if (!empty($shippingMethod['selected'])) {
                return array_values($limitedMethods);
            }
        }

        foreach (array_slice($shippingMethods, self::MAX_SHIPPING_OPTIONS) as $shippingMethod) {
            if (!empty($shippingMethod['selected'])) {
                $limitedMethods = array_slice($limitedMethods, 0, self::MAX_SHIPPING_OPTIONS - 1);
                $limitedMethods[] = $shippingMethod;
                break;
            }
        }

        return array_values($limitedMethods);
    }

    /**
     * Collect configured shipping option amounts
     *
     * The selected method is always collected. Other methods are collected only when enabled by configuration
     * and shipping is taxable.
     *
     * @param CartInterface|Quote $quote
     * @param array $shippingMethods
     * @return array
     * @throws LocalizedException
     */
    private function collectShippingOptionAmounts(
        CartInterface|Quote $quote,
        array $shippingMethods
    ): array {
        $shippingAddress = $quote->getShippingAddress();
        $selectedMethodKey = $this->getSelectedShippingMethodKey($shippingMethods);

        if (!$this->paypalConfig->isCalculateTaxForAllShippingMethodsEnabled((int) $quote->getStoreId())
            || (int) $this->taxConfig->getShippingTaxClass($quote->getStore()) === 0
        ) {
            $this->collectTotals($quote);

            return $this->updateShippingOptionAmount($shippingMethods, $selectedMethodKey, $shippingAddress);
        }

        $selectedMethodCode = $this->shippingProcessor->getShippingMethodCode(
            $shippingMethods[$selectedMethodKey]['id']
        );
        $shippingMethods = $this->filterUnavailableShippingMethods(
            $shippingMethods,
            $shippingAddress,
            $selectedMethodKey
        );

        try {
            foreach (array_keys($shippingMethods) as $key) {
                if ($key === $selectedMethodKey) {
                    continue;
                }

                $this->collectShippingMethodTotals(
                    $quote,
                    $this->shippingProcessor->getShippingMethodCode($shippingMethods[$key]['id'])
                );
                $shippingMethods = $this->updateShippingOptionAmount($shippingMethods, $key, $shippingAddress);
            }
        } catch (Exception $exception) {
            // Restore the method code so the quote remains aligned with the selected option.
            $shippingAddress->setShippingMethod($selectedMethodCode);
            throw $exception;
        }

        // The selected method is authoritative for the persisted quote and PayPal order breakdown.
        $this->collectShippingMethodTotals($quote, $selectedMethodCode);

        return $this->updateShippingOptionAmount($shippingMethods, $selectedMethodKey, $shippingAddress);
    }

    /**
     * Refresh the quote shipping rates and remove options that cannot be collected on this quote.
     *
     * @param array $shippingMethods
     * @param QuoteAddress $shippingAddress
     * @param int $selectedMethodKey
     * @return array
     * @throws LocalizedException
     */
    private function filterUnavailableShippingMethods(
        array $shippingMethods,
        QuoteAddress $shippingAddress,
        int $selectedMethodKey
    ): array {
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        $availableMethods = [];
        foreach ($shippingMethods as $key => $shippingMethod) {
            if ($key === $selectedMethodKey) {
                $availableMethods[$key] = $shippingMethod;
                continue;
            }

            $shippingMethodCode = $this->shippingProcessor->getShippingMethodCode($shippingMethod['id']);
            if ($this->isShippingMethodAvailable($shippingAddress, $shippingMethodCode)) {
                $availableMethods[$key] = $shippingMethod;
            }
        }

        return $availableMethods;
    }

    /**
     * Check whether a shipping method has a collected rate on the quote address.
     *
     * @param QuoteAddress $shippingAddress
     * @param string $shippingMethodCode
     * @return bool
     */
    private function isShippingMethodAvailable(
        QuoteAddress $shippingAddress,
        string $shippingMethodCode
    ): bool {
        foreach ($shippingAddress->getAllShippingRates() as $shippingRate) {
            if ($shippingRate->getCode() === $shippingMethodCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format shipping option amounts for PayPal
     *
     * @param array $shippingMethods
     * @return array
     */
    private function formatShippingOptionAmounts(array $shippingMethods): array
    {
        foreach (array_keys($shippingMethods) as $key) {
            $shippingMethods[$key]['amount']['value'] = $this->orderHelper->formatAmount(
                (float) $shippingMethods[$key]['amount']['value']
            );
        }

        return array_values($shippingMethods);
    }

    /**
     * Get selected shipping method key
     *
     * @param array $shippingMethods
     * @return int
     * @throws LocalizedException
     */
    private function getSelectedShippingMethodKey(array $shippingMethods): int
    {
        foreach ($shippingMethods as $key => $shippingMethod) {
            if (!empty($shippingMethod['selected'])) {
                return $key;
            }
        }

        throw new LocalizedException(__('METHOD_UNAVAILABLE'));
    }

    /**
     * Collect quote totals for a shipping method
     *
     * @param CartInterface|Quote $quote
     * @param string $shippingMethodCode
     * @return void
     */
    private function collectShippingMethodTotals(
        CartInterface|Quote $quote,
        string $shippingMethodCode
    ): void {
        $quote->getShippingAddress()->setShippingMethod($shippingMethodCode);
        $this->collectTotals($quote);
    }

    /**
     * Update a shipping option with its collected tax-inclusive amount
     *
     * @param array $shippingMethods
     * @param int $methodKey
     * @param QuoteAddress $shippingAddress
     * @return array
     */
    private function updateShippingOptionAmount(
        array $shippingMethods,
        int $methodKey,
        QuoteAddress $shippingAddress
    ): array {
        $amountInclTax = (float) $shippingAddress->getBaseShippingAmount()
            + (float) $shippingAddress->getBaseShippingTaxAmount();
        $shippingMethods[$methodKey]['amount']['value'] = $amountInclTax;

        return $shippingMethods;
    }

    /**
     * Collect quote totals
     *
     * @param CartInterface|Quote $quote
     * @return void
     */
    private function collectTotals(CartInterface|Quote $quote): void
    {
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
    }

    /**
     * Get PayPal Merchant ID
     *
     * @param Quote $quote
     * @return string
     * @throws NoSuchEntityException
     */
    private function getPayPalMerchantId(Quote $quote): string
    {
        $scopeData = $this->scopeHeaderBuilder->buildScopeHeaders($quote->getStore());

        $response = $this->merchantService->getMerchantAndPartnerInformation(
            $scopeData['x-scope-type'],
            (int) $scopeData['x-scope-id']
        );

        return $response['merchantIdentifier'];
    }

    /**
     * Get amount data
     *
     * @param CartInterface|Quote $quote
     * @return array
     */
    private function getAmountData(CartInterface|Quote $quote): array
    {
        $amountData = [
            'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
            'value' => $this->orderHelper->formatAmount((float) $quote->getBaseGrandTotal())
        ];

        $breakDown = $this->orderHelper->getAmountBreakdown($quote, $quote->getReservedOrderId());
        if (!empty($breakDown)) {
            $amountData['breakdown'] = $breakDown;
        }

        return $amountData;
    }
}

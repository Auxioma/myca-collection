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

namespace PayPal\Braintree\Model\Request\Data;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;

class GetShippingMethods
{
    /**
     * @param ShipmentEstimationInterface $shipmentEstimation
     */
    public function __construct(
        private readonly ShipmentEstimationInterface $shipmentEstimation
    ) {
    }

    /**
     * Return an array of shipping methods for PayPal
     *
     * @param CartInterface $quote
     * @return array
     * @throws InputException
     * @throws LocalizedException
     */
    public function execute(CartInterface $quote): array
    {
        $shippingAssignments = $quote->getExtensionAttributes()->getShippingAssignments();
        $shippingAssignment = array_shift($shippingAssignments);
        $quoteShippingAddress = $shippingAssignment
            ? $shippingAssignment->getShipping()->getAddress()
            : $quote->getShippingAddress();
        $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress($quote->getId(), $quoteShippingAddress);
        $filteredShippingMethods = $this->filterOutISPU($this->formatShippingMethods($quote, $shippingMethods));

        if (empty($filteredShippingMethods)) {
            throw new LocalizedException(__('METHOD_UNAVAILABLE'));
        }

        return $filteredShippingMethods;
    }

    /**
     * Format shipping methods
     *
     * @param CartInterface $quote
     * @param array $shippingMethods
     * @return array
     */
    private function formatShippingMethods(CartInterface $quote, array $shippingMethods): array
    {
        $formatted = [];
        foreach ($shippingMethods as $key => $method) {
            $selected = false;
            $shippingMethod = $method->getCarrierCode() . '_' . $method->getMethodCode();
            if (!$quote->getShippingAddress()->getShippingMethod()) {
                if ($key === 0) {
                    $selected = true;
                }
            } else {
                if ($quote->getShippingAddress()->getShippingMethod() === $shippingMethod) {
                    $selected = true;
                }
            }

            $formatted[$key] = [
                'id' => $shippingMethod,
                'amount' => [
                    'value' => number_format(round((float) $method->getPriceInclTax(), 2), 2),
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode()
                ],
                'type' => 'SHIPPING',
                'description' => $method->getCarrierTitle(),
                'selected' => $selected
            ];
        }

        return $formatted;
    }

    /**
     * Filter out In Store Pickup for PayPal express
     *
     * @param array $shippingMethods
     * @return array
     */
    private function filterOutISPU(array $shippingMethods): array
    {
        return array_values(
            array_filter($shippingMethods, static function ($method) {
                return $method['id'] !== 'instore_pickup';
            })
        );
    }
}

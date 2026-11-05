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
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ShippingAssignmentFactory;
use Magento\Quote\Model\ShippingFactory;

class ShippingProcessor
{
    private const SHIPPING_OPTION_ID_SEPARATOR = '|';

    /**
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param CartExtensionFactory $cartExtensionFactory
     * @param ShippingAssignmentFactory $shippingAssignmentFactory
     * @param ShippingFactory $shippingFactory
     */
    public function __construct(
        private readonly ShippingInformationManagementInterface $shippingInformationManagement,
        private readonly ShippingInformationInterfaceFactory $shippingInformationFactory,
        private readonly ShipmentEstimationInterface $shipmentEstimation,
        private readonly CartExtensionFactory $cartExtensionFactory,
        private readonly ShippingAssignmentFactory $shippingAssignmentFactory,
        private readonly ShippingFactory $shippingFactory
    ) {
    }

    /**
     * Process shipping options
     *
     * @param CartInterface|Quote $quote
     * @param array $shippingOption
     * @return void
     */
    public function processShippingOptions(CartInterface|Quote $quote, array $shippingOption): void
    {
        $this->handleShippingInformation($quote, $shippingOption);

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();
    }

    /**
     * Get list of shipping methods
     *
     * @param CartInterface|Quote $quote
     * @return array
     * @throws InputException
     * @throws LocalizedException
     */
    public function getShippingMethods(CartInterface|Quote $quote): array
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
     * Set default shipping method if none selected
     *
     * @param CartInterface|Quote $quote
     * @param array $shippingMethods
     * @return array
     * @throws Exception
     */
    public function setDefaultShippingMethod(
        CartInterface|Quote $quote,
        array $shippingMethods
    ): array {
        if (!$quote->getShippingAddress()->getShippingMethod() && !empty($shippingMethods)) {
            $shippingMethods[0]['selected'] = true;
            $this->prepareShippingAssignment(
                $quote,
                $this->getShippingMethodCode($shippingMethods[0]['id'])
            );
        }

        return $shippingMethods;
    }

    /**
     * Get shipping method code from PayPal shipping option ID
     *
     * @param string $shippingOptionId
     * @return string
     * @throws LocalizedException
     */
    public function getShippingMethodCode(string $shippingOptionId): string
    {
        [$carrierCode, $methodCode] = $this->parseShippingOptionId($shippingOptionId);

        return $this->buildShippingMethodCode($carrierCode, $methodCode);
    }

    /**
     * Save shipping information in Quote
     *
     * @param CartInterface|Quote $quote
     * @param array $shippingMethod
     * @return void
     */
    private function handleShippingInformation(
        CartInterface|Quote $quote,
        array $shippingMethod
    ): void {
        [$carrierCode, $methodCode] = $this->parseShippingOptionId($shippingMethod['id']);
        $magentoShippingMethodCode = $this->buildShippingMethodCode($carrierCode, $methodCode);

        /**
         * Setting up shipping method to the quote for Grouped & Bundle products
         * As this is needed for Magento/Adobe Commerce v2.4.4
         */
        $quote->getShippingAddress()->setShippingMethod($magentoShippingMethodCode);
        $quote->getShippingAddress()->setShippingAmount($shippingMethod['amount']['value']);

        $shippingInformation = $this->shippingInformationFactory->create();
        $shippingInformation->setShippingAddress($quote->getShippingAddress());
        $shippingInformation->setBillingAddress($quote->getBillingAddress());
        $shippingInformation->setShippingCarrierCode($carrierCode);
        $shippingInformation->setShippingMethodCode($methodCode);

        $this->shippingInformationManagement->saveAddressInformation(
            $quote->getId(),
            $shippingInformation
        );
    }

    /**
     * Format shipping methods
     *
     * @param CartInterface|Quote $quote
     * @param array $shippingMethods
     * @return array
     */
    private function formatShippingMethods(CartInterface|Quote $quote, array $shippingMethods): array
    {
        $formatted = [];
        $selectedMethod = $quote->getShippingAddress()->getShippingMethod();

        foreach ($shippingMethods as $key => $method) {
            $carrierCode = (string)$method->getCarrierCode();
            $methodCode = (string)$method->getMethodCode();
            $shippingMethodCode = $this->buildShippingMethodCode($carrierCode, $methodCode);
            $shippingMethodId = $this->buildShippingOptionId($carrierCode, $methodCode);
            $selected = $selectedMethod === $shippingMethodCode || $selectedMethod === $shippingMethodId;

            $formatted[$key] = [
                'id' => $shippingMethodId,
                'label' => $this->buildShippingMethodLabel($method),
                'type' => 'SHIPPING',
                'selected' => $selected,
                'amount' => [
                    'value' => round((float) $method->getBaseAmount(), 2),
                    'currency_code' => $quote->getBaseCurrencyCode()
                ]
            ];
        }

        return $formatted;
    }

    /**
     * Build PayPal shipping option ID
     *
     * @param string $carrierCode
     * @param string $methodCode
     * @return string
     */
    private function buildShippingOptionId(string $carrierCode, string $methodCode): string
    {
        return rawurlencode($carrierCode) . self::SHIPPING_OPTION_ID_SEPARATOR . rawurlencode($methodCode);
    }

    /**
     * Parse PayPal shipping option ID into carrier and method codes
     *
     * @param string $shippingOptionId
     * @return array
     * @throws LocalizedException
     */
    private function parseShippingOptionId(string $shippingOptionId): array
    {
        if (str_contains($shippingOptionId, self::SHIPPING_OPTION_ID_SEPARATOR)) {
            $shippingMethodParts = explode(self::SHIPPING_OPTION_ID_SEPARATOR, $shippingOptionId, 2);
            $shippingMethodParts = array_map('rawurldecode', $shippingMethodParts);

            if ($shippingMethodParts[0] === '' || $shippingMethodParts[1] === '') {
                throw new LocalizedException(__('METHOD_UNAVAILABLE'));
            }

            return $shippingMethodParts;
        }

        throw new LocalizedException(__('METHOD_UNAVAILABLE'));
    }

    /**
     * Build shipping method code
     *
     * @param string $carrierCode
     * @param string $methodCode
     * @return string
     */
    private function buildShippingMethodCode(string $carrierCode, string $methodCode): string
    {
        return $carrierCode . '_' . $methodCode;
    }

    /**
     * Build shipping method label from carrier and method titles
     *
     * @param ShippingMethodInterface $method
     * @return string
     */
    private function buildShippingMethodLabel(ShippingMethodInterface $method): string
    {
        $label = $method->getCarrierTitle();
        $methodTitle = $method->getMethodTitle();

        if ($methodTitle) {
            $label .= ' - ' . $methodTitle;
        }

        // PayPal's shipping_option.label has a 127-character maximum
        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        // See: https://developer.paypal.com/docs/api/orders/v2/#orders_create!path=purchase_units/shipping/options/label
        if (mb_strlen($label) > 127) {
            return mb_substr($label, 0, 124) . '...';
        }

        return $label;
    }

    /**
     * Create and save shipping assignment in Quote
     *
     * @param CartInterface|Quote $quote
     * @param string $shippingMethod
     * @return void
     * @throws Exception
     */
    private function prepareShippingAssignment(CartInterface|Quote $quote, string $shippingMethod): void
    {
        $cartExtension = $quote->getExtensionAttributes();
        if ($cartExtension === null) {
            $cartExtension = $this->cartExtensionFactory->create();
        }

        $shippingAssignments = $cartExtension->getShippingAssignments();
        if (empty($shippingAssignments)) {
            $shippingAssignment = $this->shippingAssignmentFactory->create();
        } else {
            $shippingAssignment = $shippingAssignments[0];
        }

        $shipping = $shippingAssignment->getShipping();
        if ($shipping === null) {
            $shipping = $this->shippingFactory->create();
        }

        $shipping->setAddress($quote->getShippingAddress());
        $shipping->setMethod($shippingMethod);
        $shippingAssignment->setShipping($shipping);
        $cartExtension->setShippingAssignments([$shippingAssignment]);
        $quote->setExtensionAttributes($cartExtension);

        $quote->getShippingAddress()->setShippingMethod($shippingMethod);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();
        $quote->save();
    }

    /**
     * Filter out In Store Pickup
     *
     * @param array $shippingMethods
     * @return array
     */
    private function filterOutISPU(array $shippingMethods): array
    {
        /**
         * Filter out in-store pickup, as it is not a valid shipping method for PayPal Smart Buttons.
         * Code name comes from Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup::DELIVERY_METHOD
         */
        return array_values(
            array_filter($shippingMethods, function ($method) {
                return $this->getShippingMethodCode($method['id']) !== 'instore_pickup';
            })
        );
    }
}

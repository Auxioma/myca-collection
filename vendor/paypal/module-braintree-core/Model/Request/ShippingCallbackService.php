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

namespace PayPal\Braintree\Model\Request;

use Exception;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ShippingAssignmentFactory;
use Magento\Quote\Model\ShippingFactory;
use PayPal\Braintree\Api\ShippingCallbackServiceInterface;
use PayPal\Braintree\Model\Request\Data\GetShippingMethods;
use PayPal\Braintree\Model\Request\Data\Helper\Totals;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class ShippingCallbackService implements ShippingCallbackServiceInterface
{
    private const PLACEHOLDER_FIRSTNAME = 'firstname';
    private const PLACEHOLDER_LASTNAME = 'lastname';
    private const PLACEHOLDER_TELEPHONE = '0000000000';

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param Json $json
     * @param AddressInterfaceFactory $addressFactory
     * @param RegionFactory $regionFactory
     * @param GetShippingMethods $getShippingMethods
     * @param Totals $totalsHelper
     * @param CartExtensionFactory $cartExtensionFactory
     * @param ShippingAssignmentFactory $shippingAssignmentFactory
     * @param ShippingFactory $shippingFactory
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteResolver
     * @param AllowedCountries $allowedCountries
     * @param RegionCollectionFactory $regionCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Json $json,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly RegionFactory $regionFactory,
        private readonly GetShippingMethods $getShippingMethods,
        private readonly Totals $totalsHelper,
        private readonly CartExtensionFactory $cartExtensionFactory,
        private readonly ShippingAssignmentFactory $shippingAssignmentFactory,
        private readonly ShippingFactory $shippingFactory,
        private readonly ShippingInformationManagementInterface $shippingInformationManagement,
        private readonly ShippingInformationInterfaceFactory $shippingInformationFactory,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteResolver,
        private readonly AllowedCountries $allowedCountries,
        private readonly RegionCollectionFactory $regionCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Handle PayPal shipping callback
     *
     * @param string $cartId
     * @param string $requestBody
     * @return array
     * @throws LocalizedException
     */
    public function execute(
        string $cartId,
        string $requestBody
    ): array {
        $requestData = $this->json->unserialize($requestBody);

        $this->logger->debug("CartID", [$cartId]);
        $this->logger->debug("RequestBody", $requestData);

        // Validate basic request structure
        if (!isset($requestData['id'])) {
            throw new LocalizedException(__('Missing PayPal order ID'));
        }

        $quote = $this->getQuoteByMaskedId($cartId);

        return $this->processShippingCallback(
            $quote,
            $requestData
        );
    }

    /**
     * Get quote by masked ID
     *
     * @param string $cartId
     * @return CartInterface
     * @throws LocalizedException
     */
    private function getQuoteByMaskedId(string $cartId): CartInterface
    {
        try {
            return $this->cartRepository->getActive(
                $this->maskedQuoteResolver->execute($cartId)
            );
        } catch (Exception $e) {
            throw new LocalizedException(__('Could not find cart with ID "%1"', $cartId));
        }
    }

    /**
     * Process shipping callback
     *
     * @param CartInterface $quote
     * @param array $requestData
     * @return array
     * @throws InputException
     * @throws LocalizedException
     */
    public function processShippingCallback(
        CartInterface $quote,
        array $requestData
    ): array {
        $result = [];

        $shippingAddress = $requestData['shipping_address'] ?? null;
        $shippingOption = $requestData['shipping_option'] ?? null;

        // Update shipping address and method if provided
        if ($shippingAddress || $shippingOption) {
            $result = $this->updateShippingAddressAndMethod($quote, $requestData);
        }

        return $result;
    }

    /**
     * Format shipping address and option(method) response
     *
     * @param CartInterface $quote
     * @param array $requestData
     * @return array
     * @throws InputException
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function updateShippingAddressAndMethod(
        CartInterface $quote,
        array $requestData
    ): array {
        // create and add address to the quote
        $shippingAddress = $requestData['shipping_address'] ?? null;
        if ($shippingAddress !== null) {
            $address = $this->addressFactory->create();
            $countryId = $shippingAddress['country_code'] ?? null;
            $state = $shippingAddress['admin_area_1'] ?? null;
            $postalCode = $shippingAddress['postal_code'] ?? null;

            // COUNTRY_ERROR
            $allowedCountries = $this->allowedCountries->getAllowedCountries();
            if (!in_array($countryId, $allowedCountries)) {
                throw new LocalizedException(__('COUNTRY_ERROR'));
            }

            // STATE_ERROR
            if ($state !== null) {
                $countries = $this->getCountriesWithPreDefinedRegions($allowedCountries);
                if (in_array($countryId, $countries)) {
                    $region = $this->regionFactory->create()->loadByCode($state, $countryId);
                    if (!$region->getId()) {
                        throw new LocalizedException(__('STATE_ERROR'));
                    }
                }
            }

            // ZIP_ERROR
            if ($postalCode === null || !preg_match('/^[a-zA-Z0-9\- ]+$/', $postalCode)) {
                throw new LocalizedException(__('ZIP_ERROR'));
            }

            $address->setFirstname(self::PLACEHOLDER_FIRSTNAME);
            $address->setLastname(self::PLACEHOLDER_LASTNAME);
            $address->setTelephone($shippingAddress['telephone'] ?? self::PLACEHOLDER_TELEPHONE);
            $address->setCity($shippingAddress['admin_area_2'] ?? null);
            $address->setCountryId($countryId);
            $address->setPostcode($postalCode);
            if ($state !== null) {
                $address->setRegionId($this->getRegionIdByCode($state, $countryId));
            }
            $quote->setShippingAddress($address);
            $quote->setBillingAddress($address);
            $this->cartRepository->save($quote);
        } else {
            throw new LocalizedException(__('ADDRESS_ERROR'));
        }

        // set selected shipping option to the quote
        $shippingOptions = $requestData['shipping_option'] ?? null;
        if ($shippingOptions !== null) {
            $this->handleShippingInformation($quote, $shippingOptions);

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->getShippingAddress()->collectShippingRates();
        }

        // get list of shipping methods from the quote
        $shippingMethods = $this->getShippingMethods->execute($quote);
        if ($selected = $quote->getShippingAddress()->getShippingMethod()) {
            foreach ($shippingMethods as $key => $method) {
                if ($selected === $method['id']) {
                    $shippingMethods[$key]['selected'] = true;
                }
            }
        } else {
            $shippingMethods[0]['selected'] = true;
            $this->prepareShippingAssignment($quote, $shippingMethods[0]['id']);
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $totals = $this->totalsHelper->getAmountData($quote);

        $shippingAmount = '0.00';
        foreach ($shippingMethods as $method) {
            if ($method['selected']) {
                $shippingAmount = $method['amount']['value'];
            }
        }
        $requestData['amount']['value'] = number_format(
            round(
                $shippingAmount
                + (float) $totals['breakdown']['item_total']['value']
                + (float) $totals['breakdown']['tax_total']['value']
                - (float) $totals['breakdown']['discount']['value'],
                2
            ),
            2
        );
        return [
            'id' => $requestData['id'],
            'amount' => $requestData['amount'] ?? null,
            'shipping_options' => $shippingMethods,
            'item_total' => $totals['breakdown']['item_total']['value'],
            'shipping' => $shippingAmount,
            'tax_total' => $totals['breakdown']['tax_total']['value'],
            'discount' => number_format(round($totals['breakdown']['discount']['value'], 2), 2),
        ];
    }

    /**
     * Return region ID
     *
     * @param string|null $regionCode
     * @param string|null $countryId
     * @return int
     */
    private function getRegionIdByCode(
        ?string $regionCode,
        ?string $countryId
    ): int {
        try {
            if ($regionCode === null || $countryId === null) {
                throw new LocalizedException(__('STATE_ERROR'));
            }
            $region = $this->regionFactory->create()->loadByCode($regionCode, $countryId);
        } catch (Exception $exception) {
            $this->logger->error("Failed to find region for given code", [
                'exception_message' => $exception->getMessage(),
                'region_code' => $regionCode
            ]);
            return 0;
        }

        return (int) $region->getRegionId();
    }

    /**
     * Create and save shipping assignment in Quote
     *
     * @param CartInterface $quote
     * @param string $shippingMethod
     * @return void
     */
    private function prepareShippingAssignment(CartInterface $quote, string $shippingMethod): void
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
        $this->cartRepository->save($quote);
    }

    /**
     * Save shipping information in Quote
     *
     * @param CartInterface $quote
     * @param array $shippingMethod
     * @return void
     */
    private function handleShippingInformation(
        CartInterface $quote,
        array $shippingMethod
    ): void {
        $quote->getShippingAddress()->setShippingMethod($shippingMethod['id']);
        $quote->getShippingAddress()->setShippingAmount($shippingMethod['amount']['value']);
        list($carrierCode, $methodCode) = explode('_', $shippingMethod['id']);
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
     * Get list of countries which have pre-defined regions/states
     *
     * @param array $allowedCountries
     * @return array
     */
    private function getCountriesWithPreDefinedRegions(array $allowedCountries): array
    {
        $collection = $this->regionCollectionFactory->create()
            ->addFieldToSelect('country_id')
            ->addFieldToFilter('country_id', ['in' => $allowedCountries]);
        $collection->getSelect()->distinct();

        $countries = [];

        if ($collection->getSize() > 0) {
            foreach ($collection as $region) {
                $countries[] = $region->getCountryId();
            }

            $countries = array_unique($countries);
        }

        return $countries;
    }
}

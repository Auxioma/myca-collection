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

namespace PayPal\Braintree\Model\Request\Data\Helper;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class AddressDataHelper
{
    /**
     * The address_line_1. Maximum 300 characters.
     */
    public const ADDRESS_LINE_1 = 'address_line_1';

    /**
     * The address_line_2. Maximum 300 characters.
     */
    public const ADDRESS_LINE_2 = 'address_line_2';

    /**
     * The state or province or county. The region must be a 2-letter abbreviation;
     */
    public const REGION = 'admin_area_1';

    /**
     * The locality/city. Maximum 120 characters.
     */
    public const LOCALITY = 'admin_area_2';

    /**
     * The postal code. Postal code must be a string of 5 or 9 alphanumeric digits,
     * optionally separated by a dash or a space. Spaces, hyphens,
     * and all other special characters are ignored.
     */
    public const POSTAL_CODE = 'postal_code';

    /**
     * The ISO 3166-1 2-letter country code specified in an address.
     * The gateway only accepts specific 2-letter values.
     *
     * @link https://developer.paypal.com/api/rest/reference/country-codes/
     */
    public const COUNTRY_CODE = 'country_code';

    /**
     * Get Shipping Address
     *
     * @param CartInterface $quote
     * @return array
     */
    public function getShippingAddress(CartInterface $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress->getCountryId()
            && $shippingAddress->getCity()
            && $shippingAddress->getPostcode()
        ) {
            $street = $shippingAddress->getStreet();
            $streetAddress = array_shift($street);
            $extendedAddress = implode(', ', $street);

            return [
                self::ADDRESS_LINE_1 => $streetAddress,
                self::ADDRESS_LINE_2 => $extendedAddress,
                self::LOCALITY => $shippingAddress->getCity(),
                self::REGION => $shippingAddress->getRegionCode(),
                self::POSTAL_CODE => $shippingAddress->getPostcode(),
                self::COUNTRY_CODE => $shippingAddress->getCountryId()
            ];
        }

        return [];
    }

    /**
     * Get Billing Address
     *
     * @param CartInterface $quote
     * @return array
     */
    public function getBillingAddress(CartInterface $quote): array
    {
        $billingAddress = $quote->getBillingAddress();
        if ($billingAddress->getCity()
            && $billingAddress->getCountryId()
            && $billingAddress->getPostcode()
        ) {
            $street = $billingAddress->getStreet();
            $streetAddress = array_shift($street);
            $extendedAddress = implode(', ', $street);

            return [
                self::ADDRESS_LINE_1 => $streetAddress,
                self::ADDRESS_LINE_2 => $extendedAddress,
                self::LOCALITY => $billingAddress->getCity(),
                self::REGION => $billingAddress->getRegionCode(),
                self::POSTAL_CODE => $billingAddress->getPostcode(),
                self::COUNTRY_CODE => $billingAddress->getCountryId()
            ];
        }

        return [];
    }
}

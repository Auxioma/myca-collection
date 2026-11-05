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

use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberException;
use Brick\PhoneNumber\PhoneNumberParseException;
use Brick\PhoneNumber\PhoneNumberType;
use Exception;
use Magento\Quote\Model\Quote\Address;

/**
 * Helper class for Brick PhoneNumber
 * This wrapper allows Magento 2 DI to work with 'brick/phonenumber' library
 */
class PhoneNumberHelper
{
    /**
     * Parse phone number
     *
     * @param string $number
     * @param string|null $regionCode
     * @return PhoneNumber
     * @throws PhoneNumberParseException
     */
    public function parse(string $number, ?string $regionCode = null): PhoneNumber
    {
        return PhoneNumber::parse($number, $regionCode);
    }

    /**
     * Get example number for region
     *
     * @param string $regionCode
     * @param PhoneNumberType|null $type
     * @return PhoneNumber
     * @throws PhoneNumberException
     */
    public function getExampleNumber(string $regionCode, ?PhoneNumberType $type = null): PhoneNumber
    {
        return PhoneNumber::getExampleNumber($regionCode, $type ?? PhoneNumberType::FIXED_LINE);
    }

    /**
     * Validate phone number
     *
     * @param string $number
     * @param string|null $regionCode
     * @return bool
     */
    public function isValidNumber(string $number, ?string $regionCode = null): bool
    {
        try {
            $phoneNumber = $this->parse($number, $regionCode);
            return $phoneNumber->isValidNumber();
        } catch (PhoneNumberParseException $e) {
            return false;
        }
    }

    /**
     * Get country calling code from region code
     *
     * @param string $regionCode
     * @return string|null
     */
    public function getCountryCallingCode(string $regionCode): ?string
    {
        try {
            $exampleNumber = $this->getExampleNumber($regionCode);
            return $exampleNumber->getCountryCode();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Format phone number
     *
     * @param Address $address
     * @return string
     */
    public function formatPhoneNumber(Address $address): string
    {
        $shippingTelephone = $address->getTelephone();
        if (!$shippingTelephone) {
            return '';
        }
        try {
            $telephone = $this->parse($shippingTelephone, $address->getCountryId());

            if ($telephone->isValidNumber()) {
                $phoneNumber = $telephone->getNationalNumber();

                return (string) $phoneNumber;
            } else {
                return $this->formatPhoneNumberThroughPregMatch($shippingTelephone);
            }
        } catch (PhoneNumberParseException $e) {
            return $this->formatPhoneNumberThroughPregMatch($shippingTelephone);
        }
    }

    /**
     * Format phone number through preg match pattern
     *
     * @param string $phoneNumber
     * @return string
     */
    private function formatPhoneNumberThroughPregMatch(string $phoneNumber): string
    {
        // Remove everything except digits
        $digitsOnly = preg_replace('/\D+/', '', $phoneNumber);

        // Remove only the first leading zero if present
        if (str_starts_with($digitsOnly, '0')) {
            $digitsOnly = substr($digitsOnly, 1);
        }

        return (string) $digitsOnly;
    }
}

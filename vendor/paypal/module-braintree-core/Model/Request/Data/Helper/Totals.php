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

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

class Totals
{
    /**
     * Return discount amount from Quote
     *
     * @param CartInterface $quote
     * @return array
     */
    public function getAmountData(CartInterface $quote): array
    {
        return [
            'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
            'value' => number_format(round((float) $quote->getBaseGrandTotal(), 2), 2),
            'breakdown' => [
                'item_total' => [
                    'value' => number_format(round((float) $this->getItemsTotal($quote), 2), 2),
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                ],
                'tax_total' => [
                    'value' => number_format(round((float) $this->getTaxAmount($quote), 2), 2),
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                ],
                'shipping' => [
                    'value' => number_format(round((float) $this->getShippingAmount($quote), 2), 2),
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                ],
                'discount' => [
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                    'value' => round((float) $this->getDiscountAmount($quote), 2)
                ],
            ],
        ];
    }

    /**
     * Get the quote address
     *
     * @param CartInterface $quote
     * @return AddressInterface|Address
     */
    private function getQuoteAddress(CartInterface $quote): Address|AddressInterface
    {
        $address = $quote->getShippingAddress();
        if ($quote->isVirtual()) {
            $address = $quote->getBillingAddress();
        }

        return $address;
    }

    /**
     * Return items total from Quote
     *
     * @param CartInterface $quote
     * @return float
     */
    public function getItemsTotal(CartInterface $quote): float
    {
        $subTotal = (float) $quote->getBaseSubtotal();
        $fptTotal = $this->calculateFptTotal($quote, $subTotal);
        if ($fptTotal > 0) {
            $subTotal += $fptTotal;
        }

        return (float) $subTotal;
    }

    /**
     * Calculate Fixed Product Tax(FPT) total
     *
     * @param CartInterface $quote
     * @param float $subTotal
     * @return float
     */
    private function calculateFptTotal(CartInterface $quote, float $subTotal): float
    {
        $grandTotal = $quote->getBaseGrandTotal();
        $taxTotal = $this->getTaxAmount($quote);
        $shipping = $this->getShippingAmount($quote);
        $discount = $this->getDiscountAmount($quote);

        $gwTotal = 0.00;
        if ($quote->getGwBasePrice() || $quote->getGwItemsBasePrice() || $quote->getGwCardBasePrice()) {
            $gwTotal += (float) $quote->getGwBasePrice() + $quote->getGwItemsBasePrice() + $quote->getGwCardBasePrice();
        }

        return round($grandTotal - $subTotal - $taxTotal - $shipping - $gwTotal + abs($discount), 2);
    }

    /**
     * Get shipping address
     *
     * @param CartInterface $quote
     * @return float
     */
    public function getShippingAmount(CartInterface $quote): float
    {
        $address = $this->getQuoteAddress($quote);
        return (float) $address->getBaseShippingAmount() + $address->getBaseShippingTaxAmount();
    }

    /**
     * Get discount amount
     *
     * @param CartInterface $quote
     * @return float
     */
    public function getDiscountAmount(CartInterface $quote): float
    {
        $address = $this->getQuoteAddress($quote);
        return (float) abs((float) $address->getBaseDiscountAmount());
    }

    /**
     * Get tax amount
     *
     * @param CartInterface $quote
     * @return float
     */
    public function getTaxAmount(CartInterface $quote): float
    {
        $address = $this->getQuoteAddress($quote);
        $taxAmount = round((float) $address->getBaseTaxAmount(), 2);
        if ($address->getBaseDiscountTaxCompensationAmount()) {
            $taxAmount += (float)$address->getBaseDiscountTaxCompensationAmount();
        }

        return (float) $taxAmount - (float)$address->getBaseShippingTaxAmount();
    }
}

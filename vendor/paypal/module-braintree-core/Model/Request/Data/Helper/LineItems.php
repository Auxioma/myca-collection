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

class LineItems
{
    /**
     * Return line items for order request
     *
     * @param CartInterface $quote
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getLineItems(CartInterface $quote): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            // Regex to replace all unsupported characters and manage string lengths
            $filteredFields = preg_replace(
                '/[^a-zA-Z0-9\s\-.\']/',
                '',
                [
                    'name' => substr($item->getName(), 0, 127),
                    'sku' => substr($item->getSku(), 0, 127)
                ]
            );

            $itemQuantity = (int) $item->getQty();

            /**
             * Row total calculation
             * Check FPT row amount available if so include in item row total
             */
            if ((float) $item->getWeeeTaxAppliedAmount() > 0.0) {
                $fptTotal = ($item->getWeeeTaxAppliedAmount() * $itemQuantity);
                $rowTotalCents = $this->convertToCents(
                    (float)$item->getBaseRowTotal() + (float)$fptTotal
                );
            } else {
                $rowTotalCents = $this->convertToCents((float)$item->getBaseRowTotal());
            }
            $itemUnitAmount = intdiv($rowTotalCents, $itemQuantity);
            $itemUnitRemainder = $itemUnitAmount !== 0 ? $rowTotalCents % $itemUnitAmount: 0;

            // Tax total calculation
            $taxTotalCents = $this->convertToCents((float) $item->getBaseTaxAmount());
            $itemTaxAmount = intdiv($taxTotalCents, $itemQuantity);
            $itemTaxRemainder = $itemTaxAmount !== 0 ? $taxTotalCents % $itemTaxAmount: 0;

            if ($itemUnitRemainder > 0 || $itemTaxRemainder > 0) {
                $items[] = [
                    'name' => $filteredFields['name'],
                    'unitAmount' => $this->convertToCurrency($itemUnitAmount),
                    'quantity' => $itemQuantity - 1,
                    'kind' => 'debit',
                    'unitTaxAmount' => $this->convertToCurrency($itemTaxAmount),
                    'productCode' => $filteredFields['sku']
                ];
                $unitAmountBalance = ($itemUnitRemainder >= 0) ? $itemUnitAmount + $itemUnitRemainder : $itemUnitAmount;
                $taxAmountBalance = ($itemTaxRemainder >= 0) ? $itemTaxAmount + $itemTaxRemainder : $itemTaxAmount;
                $items[] = [
                    'name' => $filteredFields['name'],
                    'unitAmount' => $this->convertToCurrency($unitAmountBalance),
                    'quantity' => 1,
                    'kind' => 'debit',
                    'unitTaxAmount' => $this->convertToCurrency($taxAmountBalance),
                    'productCode' => $filteredFields['sku']
                ];
            } else {
                $items[] = [
                    'name' => $filteredFields['name'],
                    'unitAmount' => $this->convertToCurrency($itemUnitAmount),
                    'quantity' => $itemQuantity,
                    'kind' => 'debit',
                    'unitTaxAmount' => $this->convertToCurrency($itemTaxAmount),
                    'productCode' => $filteredFields['sku']
                ];
            }
        }
        // Added Adobe Commerce Gift Wrapping feature as line items to manage totals
        if ($quote->getGwBasePrice() > 0 || $quote->getGwItemsBasePrice() > 0) {
            $items[] = [
                'name' => 'Gift Wrapping',
                'unitAmount' => round((float) $quote->getGwBasePrice() + $quote->getGwItemsBasePrice(), 2),
                'quantity' => 1,
                'kind' => 'debit'
            ];
        }
        // Added Adobe Commerce Gift Wrapping Printed Card feature as line items to manage totals
        if ($quote->getGwCardBasePrice() > 0) {
            $items[] = [
                'name' => 'Printed Card',
                'unitAmount' => round((float) $quote->getGwCardBasePrice(), 2),
                'quantity' => 1,
                'kind' => 'debit'
            ];
        }

        return $items;
    }

    /**
     * Convert the amount to integer cents
     *
     * @param float $amount
     * @return int
     */
    private function convertToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert the integer cents to float amount
     *
     * @param int $cents
     * @return float
     */
    private function convertToCurrency(int $cents): float
    {
        return round($cents / 100, 2);
    }
}

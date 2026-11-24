<?php

declare(strict_types=1);

namespace Myca\Quote\Model;

/**
 * Montants d'un devis. Les prix catalogue étant TTC, MYCA propose un prix unitaire TTC ; le HT et la TVA en sont
 * déduits sur le total, comme le fait Magento au panier : le devis et la commande affichent les mêmes montants.
 */
class QuoteCalculator
{
    /**
     * @param array<int, array{qty: float, unit_price_incl_tax: float, tax_percent: float}> $lines
     * @return array{subtotal_excl_tax: float, tax_amount: float, grand_total: float}
     */
    public function totals(array $lines): array
    {
        $inclTax = 0.0;
        $exclTax = 0.0;
        foreach ($lines as $line) {
            $row = round($line['unit_price_incl_tax'] * $line['qty'], 2);
            $inclTax += $row;
            $exclTax += round($row / (1 + $line['tax_percent'] / 100), 2);
        }

        return [
            'subtotal_excl_tax' => round($exclTax, 2),
            'tax_amount' => round($inclTax - $exclTax, 2),
            'grand_total' => round($inclTax, 2),
        ];
    }

    public function exclTax(float $inclTax, float $taxPercent): float
    {
        return round($inclTax / (1 + $taxPercent / 100), 2);
    }

    /**
     * Un devis n'est plus valable le lendemain de sa date de validité (jour calendaire de la boutique).
     */
    public function isExpired(?string $validUntil, string $today): bool
    {
        return $validUntil !== null && $validUntil !== '' && $today > substr($validUntil, 0, 10);
    }
}

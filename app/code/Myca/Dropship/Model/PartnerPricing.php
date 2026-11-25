<?php

declare(strict_types=1);

namespace Myca\Dropship\Model;

/**
 * Tarif partenaire, toujours calculé côté serveur à partir du prix public conseillé :
 * - dropshipping : une remise unitaire ;
 * - achat de stock : remise selon la quantité commandée du modèle (paliers) ;
 * une remise contractuelle propre à la boutique remplace la grille standard du mode.
 */
class PartnerPricing
{
    public const MODE_DROPSHIP = 'dropship';
    public const MODE_STOCK = 'stock';

    /**
     * @param array<int, array{min_qty: int, discount: float}> $stockTiers
     */
    public function getDiscount(string $mode, int $modelQty, ?float $contractDiscount, float $dropshipDiscount, array $stockTiers): float
    {
        if ($contractDiscount !== null) {
            return $this->clamp($contractDiscount);
        }
        if ($mode === self::MODE_DROPSHIP) {
            return $this->clamp($dropshipDiscount);
        }
        $discount = 0.0;
        $reached = 0;
        foreach ($stockTiers as $tier) {
            if ($modelQty >= $tier['min_qty'] && $tier['min_qty'] >= $reached) {
                $reached = $tier['min_qty'];
                $discount = $tier['discount'];
            }
        }

        return $this->clamp($discount);
    }

    public function getUnitPrice(float $publicPrice, float $discount): float
    {
        return round($publicPrice * (1 - $this->clamp($discount) / 100), 2);
    }

    private function clamp(float $discount): float
    {
        return max(0.0, min(90.0, $discount));
    }
}

<?php

declare(strict_types=1);

namespace Myca\Loyalty\Model;

/**
 * Calculs du programme, sans accès à la base. L'arrondi est fait une seule fois, à l'entier inférieur et par ligne :
 * le même chiffre partout (panier, espace client, administration), et un retour partiel retire exactement la part
 * de la ligne retournée.
 */
class PointsCalculator
{
    /**
     * Points d'une ligne : montant payé TTC après remises (bons fidélité compris), hors livraison.
     */
    public function pointsForLine(float $rowTotalInclTax, float $discount, float $pointsPerEuro): int
    {
        $eligible = max(0.0, $rowTotalInclTax - abs($discount));

        return (int) floor(round($eligible * $pointsPerEuro, 4));
    }

    /**
     * Points à retirer pour un remboursement, calculés sur le cumul remboursé de la ligne : des remboursements
     * successifs n'ajoutent jamais d'écart d'arrondi, et le total retiré ne dépasse jamais le gain de la ligne.
     */
    public function pointsToRemove(int $earned, float $qtyOrdered, float $qtyRefundedTotal, int $alreadyRemoved): int
    {
        if ($earned <= 0 || $qtyOrdered <= 0) {
            return 0;
        }
        $ratio = min(1.0, max(0.0, $qtyRefundedTotal / $qtyOrdered));
        $target = $ratio >= 1.0 ? $earned : (int) round($earned * $ratio);

        return max(0, min($earned, $target) - $alreadyRemoved);
    }

    /**
     * Répartit un retrait entre points en attente (d'abord) et points utilisables.
     *
     * @return array{pending: int, available: int} montants positifs à retirer
     */
    public function splitRemoval(int $toRemove, int $pendingLeftOnLine): array
    {
        $fromPending = min($toRemove, max(0, $pendingLeftOnLine));

        return ['pending' => $fromPending, 'available' => $toRemove - $fromPending];
    }

    /**
     * @return array<int, float> euros par palier de points, triés par points croissants
     */
    public static function parseRewards(string $value): array
    {
        $rewards = [];
        foreach (array_filter(array_map('trim', explode(',', $value))) as $pair) {
            if (!preg_match('/^(\d+)\s*=\s*(\d+(?:[.,]\d{1,2})?)$/', $pair, $match)) {
                return [];
            }
            $points = (int) $match[1];
            $amount = (float) str_replace(',', '.', $match[2]);
            if ($points <= 0 || $amount <= 0) {
                return [];
            }
            $rewards[$points] = $amount;
        }
        ksort($rewards);

        return $rewards;
    }

    /**
     * Prochain palier non atteint, ou null.
     *
     * @param array<int, float> $rewards
     * @return array{points: int, amount: float}|null
     */
    public function nextReward(array $rewards, int $available): ?array
    {
        foreach ($rewards as $points => $amount) {
            if ($points > $available) {
                return ['points' => $points, 'amount' => $amount];
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Myca\PartnerSales\Model;

/**
 * Calculs de M21, sans accès à la base. Une « ligne » est un article vendu en dropshipping :
 * - cost : achat MYCA HT de la ligne, frais de port de la commande répartis au prorata ;
 * - sale : vente HT au client final, ou null si aucun prix n'est connu (la ligne est alors exclue des marges).
 * La marge estimée n'est jamais la marge de MYCA ni un bénéfice certain : elle ignore les frais propres à la boutique.
 *
 * @phpstan-type Line array{order_id: int, time: int, product_id: int, name: string, qty: float, cost: float,
 *     sale: float|null, source: string|null}
 */
class MarginCalculator
{
    /**
     * Répartit un montant sur des lignes au prorata de leurs poids, au centime, sans perte d'arrondi.
     *
     * @param array<int|string, float> $weights
     * @return array<int|string, float>
     */
    public function allocate(float $amount, array $weights): array
    {
        $total = array_sum($weights);
        $keys = array_keys($weights);
        if ($total <= 0 || !$keys) {
            return array_fill_keys($keys, 0.0);
        }
        $result = [];
        $allocated = 0.0;
        $last = end($keys);
        foreach ($weights as $key => $weight) {
            $share = $key === $last ? round($amount - $allocated, 2) : round($amount * $weight / $total, 2);
            $result[$key] = $share;
            $allocated += $share;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{revenue: float, cost: float, cost_known: float, margin: float, rate: float|null, qty: float,
     *     qty_known: float, orders_known: int, basket: float|null, margin_per_item: float|null, undeclared: int,
     *     profitability: float|null}
     */
    public function summarize(array $lines, int $from, int $to): array
    {
        $revenue = $cost = $costKnown = $qty = $qtyKnown = 0.0;
        $orders = [];
        $undeclared = 0;
        foreach ($lines as $line) {
            if ($line['time'] < $from || $line['time'] >= $to) {
                continue;
            }
            $cost += $line['cost'];
            $qty += $line['qty'];
            if ($line['sale'] === null) {
                $undeclared++;
                continue;
            }
            $revenue += $line['sale'];
            $costKnown += $line['cost'];
            $qtyKnown += $line['qty'];
            $orders[$line['order_id']] = true;
        }
        $margin = round($revenue - $costKnown, 2);

        return [
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'cost_known' => round($costKnown, 2),
            'margin' => $margin,
            'rate' => $revenue > 0 ? round($margin / $revenue * 100, 1) : null,
            'qty' => $qty,
            'qty_known' => $qtyKnown,
            'orders_known' => count($orders),
            'basket' => $orders ? round($revenue / count($orders), 2) : null,
            'margin_per_item' => $qtyKnown > 0 ? round($margin / $qtyKnown, 2) : null,
            'undeclared' => $undeclared,
            'profitability' => $costKnown > 0 ? round($margin / $costKnown * 100, 1) : null,
        ];
    }

    /**
     * Par modèle, sur les lignes dont la vente est connue, triés par chiffre d'affaires décroissant.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array{product_id: int, name: string, qty: float, revenue: float, cost: float, margin: float,
     *     rate: float|null, unit_sale: float|null, unit_cost: float|null}>
     */
    public function byProduct(array $lines, int $from, int $to): array
    {
        $products = [];
        foreach ($lines as $line) {
            if ($line['sale'] === null || $line['time'] < $from || $line['time'] >= $to) {
                continue;
            }
            $id = $line['product_id'];
            $products[$id] ??= ['product_id' => $id, 'name' => $line['name'], 'qty' => 0.0, 'revenue' => 0.0, 'cost' => 0.0];
            $products[$id]['qty'] += $line['qty'];
            $products[$id]['revenue'] += $line['sale'];
            $products[$id]['cost'] += $line['cost'];
        }
        $result = [];
        foreach ($products as $product) {
            $margin = round($product['revenue'] - $product['cost'], 2);
            $result[] = $product + [
                'margin' => $margin,
                'rate' => $product['revenue'] > 0 ? round($margin / $product['revenue'] * 100, 1) : null,
                'unit_sale' => $product['qty'] > 0 ? round($product['revenue'] / $product['qty'], 2) : null,
                'unit_cost' => $product['qty'] > 0 ? round($product['cost'] / $product['qty'], 2) : null,
            ];
        }
        usort($result, static fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        return $result;
    }

    /**
     * Chiffre d'affaires connu par mois civil, sur les N derniers mois (le mois courant inclus).
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, float> AAAA-MM → montant
     */
    public function monthly(array $lines, int $months, int $now): array
    {
        $result = [];
        $start = (int) strtotime(date('Y-m-01', $now));
        for ($i = $months - 1; $i >= 0; $i--) {
            $result[date('Y-m', (int) strtotime(sprintf('-%d months', $i), $start))] = 0.0;
        }
        foreach ($lines as $line) {
            $key = date('Y-m', $line['time']);
            if ($line['sale'] !== null && isset($result[$key])) {
                $result[$key] = round($result[$key] + $line['sale'], 2);
            }
        }

        return $result;
    }

    /**
     * Évolution en %, ou null si la période de référence est vide.
     */
    public function variation(?float $current, ?float $previous): ?int
    {
        if ($current === null || $previous === null || abs($previous) < 0.005) {
            return null;
        }

        return (int) round(($current - $previous) / abs($previous) * 100);
    }
}

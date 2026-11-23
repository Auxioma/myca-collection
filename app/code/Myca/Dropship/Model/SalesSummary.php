<?php

declare(strict_types=1);

namespace Myca\Dropship\Model;

use Magento\Sales\Model\Order;

/**
 * Chiffres de la boutique sur une période (M16 « Mes performances », M21) :
 * - achats MYCA : montant HT des commandes passées à MYCA (hors annulées) ;
 * - ventes : prix de vente HT déclarés par la boutique ;
 * - marge estimée : ventes déclarées − achats MYCA correspondants (frais de port inclus), sur les seules
 *   commandes dont le prix de vente est déclaré. Ce n'est pas la marge de MYCA.
 */
class SalesSummary
{
    /**
     * @param Order[] $orders
     * @param array<int, array{mode: string, declared_sale_amount: float|null}> $declarations
     * @return array{orders: int, purchases: float, sales: float|null, margin: float|null}
     */
    public function summarize(array $orders, array $declarations, int $from, int $to): array
    {
        $count = 0;
        $purchases = 0.0;
        $sales = 0.0;
        $margin = 0.0;
        $declared = false;
        foreach ($orders as $order) {
            $createdAt = strtotime((string) $order->getCreatedAt());
            if ($createdAt < $from || $createdAt >= $to || $order->getState() === Order::STATE_CANCELED) {
                continue;
            }
            $count++;
            $amount = self::amountExclTax($order);
            $purchases += $amount;
            $declaredAmount = $declarations[(int) $order->getId()]['declared_sale_amount'] ?? null;
            if ($declaredAmount !== null) {
                $declared = true;
                $sales += $declaredAmount;
                $margin += $declaredAmount - $amount;
            }
        }

        return [
            'orders' => $count,
            'purchases' => round($purchases, 2),
            'sales' => $declared ? round($sales, 2) : null,
            'margin' => $declared ? round($margin, 2) : null,
        ];
    }

    /**
     * Variation en % entre deux périodes, ou null si la période de référence est vide.
     */
    public function variation(?float $current, ?float $previous): ?int
    {
        if ($current === null || $previous === null || abs($previous) < 0.01) {
            return null;
        }

        return (int) round(($current - $previous) / abs($previous) * 100);
    }

    public static function amountExclTax(Order $order): float
    {
        return (float) $order->getGrandTotal() - (float) $order->getTaxAmount();
    }
}

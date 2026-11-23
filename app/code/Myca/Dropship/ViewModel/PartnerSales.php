<?php

declare(strict_types=1);

namespace Myca\Dropship\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Myca\Dropship\Model\ResourceModel\DropshipOrder;
use Myca\Dropship\Model\SalesSummary;
use Myca\Partner\Model\CurrentPartner;
use Myca\Partner\Model\PartnerData;

/**
 * Ventes, marges et clients finaux du partenaire connecté (M16, M24).
 */
class PartnerSales implements ArgumentInterface
{
    /** @var array<int, array{mode: string, declared_sale_amount: float|null}>|null */
    private ?array $declarations = null;

    public function __construct(
        private readonly CurrentPartner $currentPartner,
        private readonly PartnerData $partnerData,
        private readonly DropshipOrder $dropshipOrder,
        private readonly SalesSummary $salesSummary
    ) {
    }

    /**
     * Période glissante (ex. 30 jours) et variation par rapport à la période précédente.
     *
     * @return array{current: array{orders: int, purchases: float, sales: float|null, margin: float|null},
     *     variation: array{orders: int|null, sales: int|null, margin: int|null}}
     */
    public function getPerformance(int $days = 30, ?int $now = null): array
    {
        $now ??= time();
        $from = $now - $days * 86400;

        return $this->compare($from, $now + 1, $from - $days * 86400, $from);
    }

    /**
     * Mois civil en cours, comparé au mois précédent.
     *
     * @return array{current: array{orders: int, purchases: float, sales: float|null, margin: float|null},
     *     variation: array{orders: int|null, sales: int|null, margin: int|null}}
     */
    public function getMonth(?int $now = null): array
    {
        $now ??= time();
        $start = (int) strtotime(date('Y-m-01 00:00:00', $now));
        $previous = (int) strtotime('-1 month', $start);

        return $this->compare($start, $now + 1, $previous, $start);
    }

    public function getMode(Order $order): ?string
    {
        return $this->getDeclarations()[(int) $order->getId()]['mode'] ?? null;
    }

    public function getDeclaredAmount(Order $order): ?float
    {
        return $this->getDeclarations()[(int) $order->getId()]['declared_sale_amount'] ?? null;
    }

    /**
     * Clients finaux : destinataires des commandes en dropshipping, regroupés par e-mail (ou nom + code postal).
     *
     * L'identifiant « id » est opaque (empreinte) : l'e-mail du client final n'apparaît jamais dans une URL.
     *
     * @return array<int, array{id: string, name: string, email: string, phone: string, city: string, country: string,
     *     orders: Order[], sales: float|null, last: string}>
     */
    public function getEndCustomers(): array
    {
        $partnerId = $this->currentPartner->getActiveId();
        if ($partnerId === null) {
            return [];
        }
        $customers = [];
        foreach ($this->partnerData->getOrders($partnerId) as $order) {
            $address = $order->getShippingAddress();
            if ($address === null || $this->getMode($order) !== DropshipOrder::MODE_DROPSHIP) {
                continue;
            }
            $email = mb_strtolower(trim((string) $address->getEmail()));
            $key = $email !== '' ? $email : mb_strtolower($address->getName() . '|' . $address->getPostcode());
            $customers[$key] ??= [
                'id' => substr(hash('sha256', $partnerId . '|' . $key), 0, 16),
                'name' => (string) $address->getName(),
                'email' => $email,
                'phone' => (string) $address->getTelephone(),
                'city' => (string) $address->getCity(),
                'country' => (string) $address->getCountryId(),
                'orders' => [],
                'sales' => null,
                'last' => (string) $order->getCreatedAt(),
            ];
            $customers[$key]['orders'][] = $order;
            $declared = $this->getDeclaredAmount($order);
            if ($declared !== null && $order->getState() !== Order::STATE_CANCELED) {
                $customers[$key]['sales'] = ($customers[$key]['sales'] ?? 0.0) + $declared;
            }
        }

        return array_values($customers);
    }

    /**
     * @return array{current: array{orders: int, purchases: float, sales: float|null, margin: float|null},
     *     variation: array{orders: int|null, sales: int|null, margin: int|null}}
     */
    private function compare(int $from, int $to, int $previousFrom, int $previousTo): array
    {
        $partnerId = $this->currentPartner->getActiveId();
        $orders = $partnerId === null ? [] : $this->partnerData->getOrders($partnerId);
        $current = $this->salesSummary->summarize($orders, $this->getDeclarations(), $from, $to);
        $previous = $this->salesSummary->summarize($orders, $this->getDeclarations(), $previousFrom, $previousTo);

        return [
            'current' => $current,
            'variation' => [
                'orders' => $this->salesSummary->variation((float) $current['orders'], $previous['orders'] ? (float) $previous['orders'] : null),
                'sales' => $this->salesSummary->variation($current['sales'], $previous['sales']),
                'margin' => $this->salesSummary->variation($current['margin'], $previous['margin']),
            ],
        ];
    }

    /**
     * @return array<int, array{mode: string, declared_sale_amount: float|null}>
     */
    private function getDeclarations(): array
    {
        if ($this->declarations === null) {
            $partnerId = $this->currentPartner->getActiveId();
            $this->declarations = $partnerId === null ? [] : $this->dropshipOrder->getForPartner($partnerId);
        }

        return $this->declarations;
    }
}

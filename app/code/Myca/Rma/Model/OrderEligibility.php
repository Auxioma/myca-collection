<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Myca\Core\Setup\Patch\Data\AddDeliveredOrderStatus;
use Myca\Dropship\Model\ResourceModel\DropshipOrder;
use Myca\Partner\Model\ResourceModel\PartnerOrder;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;

/**
 * Ce qu'une commande permet de retourner : origine, délai, quantités encore disponibles.
 */
class OrderEligibility
{
    public function __construct(
        private readonly ReturnPolicy $policy,
        private readonly StatusFlow $statusFlow,
        private readonly Config $config,
        private readonly PartnerOrder $partnerOrder,
        private readonly DropshipOrder $dropshipOrder,
        private readonly RmaResource $rmaResource,
        private readonly ResourceConnection $resource,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function getPartnerId(Order $order): ?int
    {
        return $this->partnerOrder->getPartnerId((int) $order->getId());
    }

    /**
     * Origine de la vente. Null pour une commande partenaire dont le mode de vente n'a pas été déclaré : la boutique
     * l'indique alors dans sa demande.
     */
    public function getOrigin(Order $order): ?string
    {
        if ($this->getPartnerId($order) === null) {
            return ReturnPolicy::ORIGIN_ONLINE;
        }

        return match ($this->dropshipOrder->getMode((int) $order->getId())) {
            DropshipOrder::MODE_DROPSHIP => ReturnPolicy::ORIGIN_DROPSHIP,
            DropshipOrder::MODE_STOCK => ReturnPolicy::ORIGIN_STORE,
            default => null,
        };
    }

    /**
     * Date de livraison confirmée (passage au statut « Livrée »), ou null. Elle est exprimée dans le fuseau de la
     * boutique pour que le délai se termine à minuit, heure locale.
     */
    public function getDeliveredAt(Order $order): ?\DateTimeImmutable
    {
        $connection = $this->resource->getConnection();
        $date = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('sales_order_status_history'), ['MIN(created_at)'])
                ->where('parent_id = ?', (int) $order->getId())
                ->where('status = ?', AddDeliveredOrderStatus::STATUS)
        );
        if (!$date && $order->getStatus() === AddDeliveredOrderStatus::STATUS) {
            $date = $order->getUpdatedAt();
        }

        if (!is_string($date) || $date === '') {
            return null;
        }
        $timezone = new \DateTimeZone(
            (string) $this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, (string) $order->getStoreId())
        );

        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->setTimezone($timezone);
    }

    public function getPolicyDays(Order $order, string $type): int
    {
        $storeId = (int) $order->getStoreId();

        return $this->policy->getPolicyDays(
            $type,
            $this->config->isCommercial30DaysEnabled($storeId),
            $this->config->getWarrantyDays($storeId)
        );
    }

    public function getDeadline(Order $order, string $type): ?\DateTimeImmutable
    {
        return $this->policy->getDeadline($this->getDeliveredAt($order), $this->getPolicyDays($order, $type));
    }

    /**
     * Quantités encore retournables par ligne visible : expédiées, moins remboursées, moins déjà demandées.
     *
     * @return array<int, float>
     */
    public function getReturnableQtys(Order $order): array
    {
        $requested = $this->rmaResource->getRequestedQtys((int) $order->getId(), $this->getHoldingStatuses());
        $result = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $itemId = (int) $item->getItemId();
            $qty = (float) $item->getQtyShipped() - (float) $item->getQtyRefunded() - ($requested[$itemId] ?? 0.0);
            $result[$itemId] = max(0.0, $qty);
        }

        return $result;
    }

    /**
     * Raisons pour lesquelles aucune demande ne peut être ouverte (vide si la commande est éligible).
     *
     * @return string[]
     */
    public function getBlockingReasons(Order $order, ?string $type = null, ?\DateTimeImmutable $now = null): array
    {
        $reasons = [];
        if (!$order->hasShipments()) {
            $reasons[] = (string) __('Cette commande n’a pas encore été expédiée : pour l’annuler, contactez le service client.');
        } elseif (array_sum($this->getReturnableQtys($order)) <= 0) {
            $reasons[] = (string) __('Tous les articles de cette commande font déjà l’objet d’une demande ou d’un remboursement.');
        }
        if ($type !== null && !$this->policy->isWithinDeadline(
            $this->getDeliveredAt($order),
            $this->getPolicyDays($order, $type),
            $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        )) {
            $reasons[] = $type === ReturnPolicy::TYPE_WARRANTY
                ? (string) __('Le délai de garantie de cette commande est dépassé.')
                : (string) __('Le délai de retour de %1 jours après la livraison est dépassé. Un défaut reste couvert par la garantie : choisissez « SAV ».', $this->getPolicyDays($order, $type));
        }

        return $reasons;
    }

    /**
     * @return string[]
     */
    private function getHoldingStatuses(): array
    {
        return array_values(array_filter(
            array_keys($this->statusFlow->getLabels()),
            fn (string $status) => $this->statusFlow->holdsQuantities($status)
        ));
    }
}

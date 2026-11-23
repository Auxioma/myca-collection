<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use Myca\Partner\Model\ResourceModel\PartnerOrder;

/**
 * Lecture cloisonnée des données d'un partenaire : chaque requête est jointe à myca_partner_order
 * et filtrée sur partner_id. Aucune méthode ne renvoie de donnée d'un autre partenaire.
 */
class PartnerData
{
    /** @var array<int, Order[]> */
    private array $orders = [];

    public function __construct(
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly InvoiceCollectionFactory $invoiceCollectionFactory,
        private readonly ShipmentCollectionFactory $shipmentCollectionFactory,
        private readonly PartnerOrder $partnerOrder,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Commandes du partenaire, de la plus récente à la plus ancienne.
     *
     * @return Order[]
     */
    public function getOrders(int $partnerId): array
    {
        if (!isset($this->orders[$partnerId])) {
            $collection = $this->orderCollectionFactory->create();
            $collection->getSelect()->join(
                ['mpo' => $this->partnerOrder->getTableName()],
                'mpo.order_id = main_table.entity_id',
                []
            )->where('mpo.partner_id = ?', $partnerId);
            $collection->setOrder('created_at', 'desc');
            /** @var Order[] $items */
            $items = $collection->getItems();
            $this->orders[$partnerId] = array_values($items);
        }

        return $this->orders[$partnerId];
    }

    public function getOrder(int $partnerId, int $orderId): ?Order
    {
        foreach ($this->getOrders($partnerId) as $order) {
            if ((int) $order->getId() === $orderId) {
                return $order;
            }
        }

        return null;
    }

    public function ownsOrder(int $partnerId, int $orderId): bool
    {
        return $this->partnerOrder->getPartnerId($orderId) === $partnerId;
    }

    /**
     * @return Invoice[]
     */
    public function getInvoices(int $partnerId): array
    {
        $collection = $this->invoiceCollectionFactory->create();
        $collection->getSelect()->join(
            ['mpo' => $this->partnerOrder->getTableName()],
            'mpo.order_id = main_table.order_id',
            []
        )->where('mpo.partner_id = ?', $partnerId);
        $collection->setOrder('created_at', 'desc');
        /** @var Invoice[] $items */
        $items = $collection->getItems();

        return array_values($items);
    }

    /**
     * @return Shipment[]
     */
    public function getShipments(int $partnerId): array
    {
        $collection = $this->shipmentCollectionFactory->create();
        $collection->getSelect()->join(
            ['mpo' => $this->partnerOrder->getTableName()],
            'mpo.order_id = main_table.order_id',
            []
        )->where('mpo.partner_id = ?', $partnerId);
        $collection->setOrder('created_at', 'desc');
        /** @var Shipment[] $items */
        $items = $collection->getItems();

        return array_values($items);
    }

    /**
     * Produits les plus commandés (lignes parentes), avec le nombre de commandes et la quantité.
     *
     * @return array<int, array{product_id: int, name: string, orders: int, qty: float}>
     */
    public function getTopProducts(int $partnerId, int $limit = 3, ?string $since = null): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['soi' => $this->resource->getTableName('sales_order_item')], [
                'product_id' => 'soi.product_id',
                'name' => 'MAX(soi.name)',
                'orders' => 'COUNT(DISTINCT soi.order_id)',
                'qty' => 'SUM(soi.qty_ordered)',
            ])
            ->join(['mpo' => $this->partnerOrder->getTableName()], 'mpo.order_id = soi.order_id', [])
            ->join(['so' => $this->resource->getTableName('sales_order')], 'so.entity_id = soi.order_id', [])
            ->where('mpo.partner_id = ?', $partnerId)
            ->where('soi.parent_item_id IS NULL')
            ->where('so.state <> ?', Order::STATE_CANCELED)
            ->group('soi.product_id')
            ->order(['orders DESC', 'qty DESC'])
            ->limit($limit);
        if ($since !== null) {
            $select->where('so.created_at >= ?', $since);
        }

        return array_map(static fn (array $row) => [
            'product_id' => (int) $row['product_id'],
            'name' => (string) $row['name'],
            'orders' => (int) $row['orders'],
            'qty' => (float) $row['qty'],
        ], $connection->fetchAll($select));
    }

    /**
     * Comptes clients rattachés au partenaire (équipe M26).
     *
     * @return array<int, array{customer_id: int, firstname: string, lastname: string, email: string, role: string}>
     */
    public function getUsers(int $partnerId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['mpu' => $this->resource->getTableName('myca_partner_user')], ['customer_id', 'role'])
            ->join(['ce' => $this->resource->getTableName('customer_entity')], 'ce.entity_id = mpu.customer_id', [
                'firstname', 'lastname', 'email',
            ])
            ->where('mpu.partner_id = ?', $partnerId)
            ->order('mpu.partner_user_id ASC');

        return array_map(static fn (array $row) => [
            'customer_id' => (int) $row['customer_id'],
            'firstname' => (string) $row['firstname'],
            'lastname' => (string) $row['lastname'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
        ], $connection->fetchAll($select));
    }
}

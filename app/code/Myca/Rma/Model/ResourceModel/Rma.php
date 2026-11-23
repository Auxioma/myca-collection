<?php

declare(strict_types=1);

namespace Myca\Rma\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Tables myca_rma, myca_rma_item et myca_rma_history.
 */
class Rma extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_rma', 'rma_id');
    }

    public function connection(): AdapterInterface
    {
        $connection = $this->getConnection();
        if (!$connection instanceof AdapterInterface) {
            throw new \RuntimeException('Connexion à la base indisponible.');
        }

        return $connection;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(int $rmaId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->getTable('myca_rma_item'))->where('rma_id = ?', $rmaId)->order('rma_item_id')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function insertItems(int $rmaId, array $items): void
    {
        foreach ($items as $item) {
            $this->connection()->insert($this->getTable('myca_rma_item'), ['rma_id' => $rmaId] + $item);
        }
    }

    public function setItemCondition(int $rmaId, int $rmaItemId, ?string $condition): void
    {
        $this->connection()->update(
            $this->getTable('myca_rma_item'),
            ['item_condition' => $condition],
            ['rma_id = ?' => $rmaId, 'rma_item_id = ?' => $rmaItemId]
        );
    }

    /**
     * Quantités déjà engagées dans des demandes en cours ou abouties, par ligne de commande. Les demandes
     * remboursées sont exclues : leurs quantités figurent déjà dans qty_refunded de la commande.
     *
     * @param string[] $holdingStatuses
     * @return array<int, float>
     */
    public function getRequestedQtys(int $orderId, array $holdingStatuses): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['item' => $this->getTable('myca_rma_item')], ['order_item_id', 'qty' => 'SUM(item.qty)'])
            ->join(['rma' => $this->getMainTable()], 'rma.rma_id = item.rma_id', [])
            ->where('rma.order_id = ?', $orderId)
            ->where('rma.status IN (?)', $holdingStatuses)
            ->where('rma.creditmemo_id IS NULL')
            ->group('item.order_item_id');
        $result = [];
        foreach ($connection->fetchPairs($select) as $itemId => $qty) {
            $result[(int) $itemId] = (float) $qty;
        }

        return $result;
    }

    /**
     * Verrouille la commande le temps de créer une demande : deux demandes simultanées ne peuvent pas retourner la
     * même pièce deux fois.
     */
    public function lockOrder(int $orderId): void
    {
        $connection = $this->connection();
        $connection->fetchOne(
            $connection->select()->from($this->getTable('sales_order'), ['entity_id'])->where('entity_id = ?', $orderId)->forUpdate(true)
        );
    }

    /**
     * Relit l'état en base en le verrouillant : un remboursement rejoué ou concurrent voit l'avoir déjà émis.
     *
     * @return array<string, mixed>
     */
    public function lockRow(int $rmaId): array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->getMainTable())->where('rma_id = ?', $rmaId)->forUpdate(true)
        );

        return is_array($row) ? $row : [];
    }

    public function addHistory(int $rmaId, string $status, string $authorType, string $authorName, ?string $message, bool $visible = true): void
    {
        $this->connection()->insert($this->getTable('myca_rma_history'), [
            'rma_id' => $rmaId,
            'status' => $status,
            'author_type' => $authorType,
            'author_name' => $authorName,
            'message' => $message,
            'is_visible' => (int) $visible,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $rmaId, bool $visibleOnly): array
    {
        $connection = $this->connection();
        $select = $connection->select()->from($this->getTable('myca_rma_history'))->where('rma_id = ?', $rmaId)->order('history_id');
        if ($visibleOnly) {
            $select->where('is_visible = 1');
        }

        return $connection->fetchAll($select);
    }
}

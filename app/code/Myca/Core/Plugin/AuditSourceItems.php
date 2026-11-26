<?php

declare(strict_types=1);

namespace Myca\Core\Plugin;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Myca\Core\Model\AuditLogger;

/**
 * Stocks : chaque changement de quantité ou de statut d'une source est consigné avec l'ancienne et la nouvelle valeur.
 */
class AuditSourceItems
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly AuditLogger $audit
    ) {
    }

    /**
     * @param SourceItemInterface[] $sourceItems
     */
    public function aroundExecute(SourceItemsSaveInterface $subject, callable $proceed, array $sourceItems): void
    {
        $before = [];
        if ($sourceItems !== []) {
            $connection = $this->resource->getConnection();
            $select = $connection->select()->from($this->resource->getTableName('inventory_source_item'), ['sku', 'source_code', 'quantity', 'status']);
            $where = [];
            foreach ($sourceItems as $item) {
                $where[] = $connection->quoteInto('(sku = ?', $item->getSku()) . $connection->quoteInto(' AND source_code = ?)', $item->getSourceCode());
            }
            foreach ($connection->fetchAll($select->where(implode(' OR ', $where))) as $row) {
                $before[$row['sku'] . '|' . $row['source_code']] = $row;
            }
        }
        $proceed($sourceItems);
        foreach ($sourceItems as $item) {
            $old = $before[$item->getSku() . '|' . $item->getSourceCode()] ?? null;
            $oldValues = $old !== null ? ['quantity' => (float) $old['quantity'], 'status' => (int) $old['status']] : null;
            $newValues = ['quantity' => (float) $item->getQuantity(), 'status' => (int) $item->getStatus()];
            if ($oldValues != $newValues) {
                $this->audit->log('stock_changed', 'source_item', $item->getSku() . ' @ ' . $item->getSourceCode(), $oldValues, $newValues);
            }
        }
    }
}

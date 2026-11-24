<?php

declare(strict_types=1);

namespace Myca\Quote\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Tables myca_quote, myca_quote_item et myca_quote_history.
 */
class Quote extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_quote', 'quote_id');
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
    public function getItems(int $quoteId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->getTable('myca_quote_item'))->where('quote_id = ?', $quoteId)->order('item_id')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function insertItems(int $quoteId, array $items): void
    {
        foreach ($items as $item) {
            $this->connection()->insert($this->getTable('myca_quote_item'), ['quote_id' => $quoteId] + $item);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateItem(int $quoteId, int $itemId, array $data): void
    {
        $this->connection()->update($this->getTable('myca_quote_item'), $data, ['quote_id = ?' => $quoteId, 'item_id = ?' => $itemId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function lockRow(int $quoteId): array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->getMainTable())->where('quote_id = ?', $quoteId)->forUpdate(true));

        return is_array($row) ? $row : [];
    }

    public function addHistory(int $quoteId, string $status, string $author, ?string $message): void
    {
        $this->connection()->insert($this->getTable('myca_quote_history'), [
            'quote_id' => $quoteId,
            'status' => $status,
            'author' => $author,
            'message' => $message,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $quoteId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->getTable('myca_quote_history'))->where('quote_id = ?', $quoteId)->order('history_id')
        );
    }
}

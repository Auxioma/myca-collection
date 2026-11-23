<?php

declare(strict_types=1);

namespace Myca\Loyalty\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Journal fidélité en ajout seul. Chaque mouvement verrouille le solde du client, vérifie que sa clé d'unicité
 * n'a pas déjà été enregistrée, puis écrit la ligne (avec les soldes avant / après) et le nouveau solde.
 * Aucune méthode ne modifie ni ne supprime une ligne du journal.
 */
class Ledger
{
    public const EARN = 'earn';
    public const VALIDATE = 'validate';
    public const CANCEL = 'cancel';
    public const REFUND = 'refund';
    public const REDEEM = 'redeem';
    public const RESTORE = 'restore';
    public const EXPIRE = 'expire';
    public const ADJUST = 'adjust';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Enregistre un mouvement. Renvoie false s'il l'a déjà été (événement rejoué) : rien n'est alors modifié.
     *
     * @param array{key: string, customer_id: int, event: string, pending_delta?: int, available_delta?: int,
     *     reason: string, author: string, order_id?: int|null, order_increment_id?: string|null,
     *     order_item_id?: int|null, creditmemo_id?: int|null, voucher_id?: int|null} $movement
     */
    public function record(array $movement): bool
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $account = $this->lockAccount($movement['customer_id']);
            if ($this->hasKey($movement['key'])) {
                $connection->commit();

                return false;
            }
            $pendingDelta = (int) ($movement['pending_delta'] ?? 0);
            $availableDelta = (int) ($movement['available_delta'] ?? 0);
            $after = [
                'pending' => $account['pending'] + $pendingDelta,
                'available' => $account['available'] + $availableDelta,
            ];
            $connection->insert($this->table('myca_loyalty_transaction'), [
                'idempotency_key' => $movement['key'],
                'customer_id' => $movement['customer_id'],
                'order_id' => $movement['order_id'] ?? null,
                'order_increment_id' => $movement['order_increment_id'] ?? null,
                'order_item_id' => $movement['order_item_id'] ?? null,
                'creditmemo_id' => $movement['creditmemo_id'] ?? null,
                'voucher_id' => $movement['voucher_id'] ?? null,
                'event' => $movement['event'],
                'pending_delta' => $pendingDelta,
                'available_delta' => $availableDelta,
                'pending_before' => $account['pending'],
                'pending_after' => $after['pending'],
                'available_before' => $account['available'],
                'available_after' => $after['available'],
                'reason' => mb_substr($movement['reason'], 0, 255),
                'author' => mb_substr($movement['author'], 0, 255),
            ]);
            $connection->update($this->table('myca_loyalty_account'), $after, ['customer_id = ?' => $movement['customer_id']]);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Verrouille le solde du client jusqu'à la fin de la transaction en cours (le crée au besoin).
     *
     * @return array{available: int, pending: int}
     */
    public function lockAccount(int $customerId): array
    {
        $connection = $this->connection();
        $table = $this->table('myca_loyalty_account');
        $connection->insertOnDuplicate($table, ['customer_id' => $customerId], ['customer_id']);
        $row = $connection->fetchRow(
            $connection->select()->from($table, ['available', 'pending'])->where('customer_id = ?', $customerId)->forUpdate(true)
        );

        return ['available' => (int) ($row['available'] ?? 0), 'pending' => (int) ($row['pending'] ?? 0)];
    }

    /**
     * @return array{available: int, pending: int}
     */
    public function getAccount(int $customerId): array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->table('myca_loyalty_account'), ['available', 'pending'])->where('customer_id = ?', $customerId)
        );

        return ['available' => (int) ($row['available'] ?? 0), 'pending' => (int) ($row['pending'] ?? 0)];
    }

    /**
     * État d'une ligne de commande : points gagnés, encore en attente, déjà retirés (annulation, remboursement).
     *
     * @return array{earned: int, pending_left: int, removed: int}
     */
    public function getLineState(int $orderItemId): array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->table('myca_loyalty_transaction'), [
                    'earned' => new \Zend_Db_Expr("SUM(IF(event = 'earn', pending_delta, 0))"),
                    'pending_left' => new \Zend_Db_Expr('SUM(pending_delta)'),
                    'removed' => new \Zend_Db_Expr("-SUM(IF(event IN ('cancel', 'refund'), pending_delta + available_delta, 0))"),
                ])
                ->where('order_item_id = ?', $orderItemId)
        );

        return [
            'earned' => (int) ($row['earned'] ?? 0),
            'pending_left' => (int) ($row['pending_left'] ?? 0),
            'removed' => (int) ($row['removed'] ?? 0),
        ];
    }

    /**
     * Commandes ayant encore des points en attente.
     *
     * @return int[]
     */
    public function getOrdersWithPendingPoints(): array
    {
        $connection = $this->connection();

        $select = $connection->select()
            ->from($this->table('myca_loyalty_transaction'), ['order_id'])
            ->where('order_id IS NOT NULL');
        $select->group('order_id');
        $select->having('SUM(pending_delta) > 0');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Clients avec des points utilisables et aucun gain depuis la date donnée.
     *
     * @return array<int, int> dernier transaction_id par client
     */
    public function getInactiveCustomers(string $since): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['account' => $this->table('myca_loyalty_account')], ['customer_id'])
            ->join(['txn' => $this->table('myca_loyalty_transaction')], 'txn.customer_id = account.customer_id', ['last_id' => 'MAX(txn.transaction_id)'])
            ->where('account.available > 0');
        $select->group('account.customer_id');
        $select->having("MAX(IF(txn.event IN ('earn', 'validate', 'adjust'), txn.created_at, NULL)) < ?", $since);
        $result = [];
        foreach ($connection->fetchPairs($select) as $customerId => $lastId) {
            $result[(int) $customerId] = (int) $lastId;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $customerId, ?int $limit = null): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table('myca_loyalty_transaction'))
            ->where('customer_id = ?', $customerId)
            ->order('transaction_id DESC');
        if ($limit !== null) {
            $select->limit($limit);
        }

        return $connection->fetchAll($select);
    }

    /**
     * Totaux affichés au client : utilisés, annulés (annulation ou retour), expirés.
     *
     * @return array{used: int, canceled: int, expired: int}
     */
    public function getTotals(int $customerId): array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->table('myca_loyalty_transaction'), [
                    'used' => new \Zend_Db_Expr("-SUM(IF(event = 'redeem', available_delta, 0)) - SUM(IF(event = 'restore', available_delta, 0))"),
                    'canceled' => new \Zend_Db_Expr("-SUM(IF(event IN ('cancel', 'refund'), pending_delta + available_delta, 0))"),
                    'expired' => new \Zend_Db_Expr("-SUM(IF(event = 'expire', available_delta, 0))"),
                ])
                ->where('customer_id = ?', $customerId)
        );

        return ['used' => (int) ($row['used'] ?? 0), 'canceled' => (int) ($row['canceled'] ?? 0), 'expired' => (int) ($row['expired'] ?? 0)];
    }

    private function hasKey(string $key): bool
    {
        $connection = $this->connection();

        return (bool) $connection->fetchOne(
            $connection->select()->from($this->table('myca_loyalty_transaction'), ['transaction_id'])->where('idempotency_key = ?', $key)
        );
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}

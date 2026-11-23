<?php

declare(strict_types=1);

namespace Myca\Partner\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Partner extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_partner', 'partner_id');
    }

    /**
     * Identifiant du partenaire d'un compte client, ou null.
     */
    public function getPartnerIdByCustomerId(int $customerId): ?int
    {
        $connection = $this->connection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->getTable('myca_partner_user'), ['partner_id'])
                ->where('customer_id = ?', $customerId)
        );

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Préférences de notification d’N utilisateur partenaire (M26).
     *
     * @return array<string, bool>
     */
    public function getUserPreferences(int $customerId): array
    {
        $connection = $this->connection();
        $value = (string) $connection->fetchOne(
            $connection->select()
                ->from($this->getTable('myca_partner_user'), ['preferences'])
                ->where('customer_id = ?', $customerId)
        );
        $decoded = $value === '' ? [] : json_decode($value, true);

        return is_array($decoded) ? array_map('boolval', $decoded) : [];
    }

    /**
     * @param array<string, bool> $preferences
     */
    public function saveUserPreferences(int $customerId, array $preferences): void
    {
        $this->connection()->update(
            $this->getTable('myca_partner_user'),
            ['preferences' => json_encode($preferences, JSON_THROW_ON_ERROR)],
            ['customer_id = ?' => $customerId]
        );
    }

    /**
     * Rattache un compte client à un partenaire (un compte n'appartient qu'à un partenaire).
     */
    public function linkCustomer(int $partnerId, int $customerId, string $role = 'owner'): void
    {
        $this->connection()->insertOnDuplicate(
            $this->getTable('myca_partner_user'),
            ['partner_id' => $partnerId, 'customer_id' => $customerId, 'role' => $role],
            ['partner_id', 'role']
        );
    }

    /**
     * Rôle d'un compte client chez un partenaire (owner, staff), ou null s'il n'en fait pas partie.
     */
    public function getUserRole(int $partnerId, int $customerId): ?string
    {
        $connection = $this->connection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->getTable('myca_partner_user'), ['role'])
                ->where('partner_id = ?', $partnerId)
                ->where('customer_id = ?', $customerId)
        );

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function connection(): AdapterInterface
    {
        $connection = $this->getConnection();
        if (!$connection instanceof AdapterInterface) {
            throw new \RuntimeException('Connexion à la base indisponible.');
        }

        return $connection;
    }
}

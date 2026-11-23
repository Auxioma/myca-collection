<?php

declare(strict_types=1);

namespace Myca\Partner\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Table de rattachement commande → partenaire (myca_partner_order) : base du cloisonnement des commandes,
 * factures et expéditions d'un partenaire.
 */
class PartnerOrder
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function link(int $orderId, int $partnerId): void
    {
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName('myca_partner_order'),
            ['order_id' => $orderId, 'partner_id' => $partnerId],
            []
        );
    }

    public function getPartnerId(int $orderId): ?int
    {
        $connection = $this->resource->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('myca_partner_order'), ['partner_id'])
                ->where('order_id = ?', $orderId)
        );

        return is_numeric($value) ? (int) $value : null;
    }

    public function getTableName(): string
    {
        return $this->resource->getTableName('myca_partner_order');
    }
}

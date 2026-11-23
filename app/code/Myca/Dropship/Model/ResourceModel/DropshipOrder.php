<?php

declare(strict_types=1);

namespace Myca\Dropship\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Lecture / écriture de myca_dropship_order, toujours filtrée par partenaire.
 */
class DropshipOrder
{
    public const MODE_DROPSHIP = 'dropship';
    public const MODE_STOCK = 'stock';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function save(
        int $orderId,
        int $partnerId,
        string $mode,
        ?float $declaredSaleAmount = null,
        bool $hidePricesOnPackingSlip = true
    ): void {
        if (!in_array($mode, [self::MODE_DROPSHIP, self::MODE_STOCK], true)) {
            throw new \InvalidArgumentException(sprintf('Mode de vente inconnu : %s', $mode));
        }
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName('myca_dropship_order'),
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'mode' => $mode,
                'ship_to_end_customer' => (int) ($mode === self::MODE_DROPSHIP),
                'packing_slip_hide_prices' => (int) $hidePricesOnPackingSlip,
                'declared_sale_amount' => $declaredSaleAmount,
            ],
            ['mode', 'ship_to_end_customer', 'packing_slip_hide_prices', 'declared_sale_amount']
        );
    }

    /**
     * Mode de vente d'une commande, ou null s'il n'a pas été déclaré.
     */
    public function getMode(int $orderId): ?string
    {
        $connection = $this->resource->getConnection();
        $mode = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('myca_dropship_order'), ['mode'])
                ->where('order_id = ?', $orderId)
        );

        return is_string($mode) && $mode !== '' ? $mode : null;
    }

    /**
     * @return array<int, array{mode: string, declared_sale_amount: float|null}> indexé par order_id
     */
    public function getForPartner(int $partnerId): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('myca_dropship_order'), ['order_id', 'mode', 'declared_sale_amount'])
                ->where('partner_id = ?', $partnerId)
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['order_id']] = [
                'mode' => (string) $row['mode'],
                'declared_sale_amount' => $row['declared_sale_amount'] === null ? null : (float) $row['declared_sale_amount'],
            ];
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Myca\Dropship\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Commande partenaire en cours (myca_dropship_quote) : mode, destinataire final, prix de vente déclaré.
 */
class PartnerQuote
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Json $json
    ) {
    }

    /**
     * @return array{partner_id: int, mode: string, recipient: array<string, string>|null, declared_sale_amount: float|null}|null
     */
    public function get(int $quoteId): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->resource->getTableName('myca_dropship_quote'))->where('quote_id = ?', $quoteId)
        );
        if (!is_array($row)) {
            return null;
        }
        $recipient = $row['recipient'] ? $this->json->unserialize((string) $row['recipient']) : null;

        return [
            'partner_id' => (int) $row['partner_id'],
            'mode' => (string) $row['mode'],
            'recipient' => is_array($recipient) ? array_map('strval', $recipient) : null,
            'declared_sale_amount' => $row['declared_sale_amount'] !== null ? (float) $row['declared_sale_amount'] : null,
        ];
    }

    /**
     * @param array{mode?: string, recipient?: array<string, string>|null, declared_sale_amount?: float|null} $data
     */
    public function save(int $quoteId, int $partnerId, array $data): void
    {
        $row = ['quote_id' => $quoteId, 'partner_id' => $partnerId];
        if (array_key_exists('mode', $data)) {
            $row['mode'] = $data['mode'];
        }
        if (array_key_exists('recipient', $data)) {
            $row['recipient'] = $data['recipient'] !== null ? $this->json->serialize($data['recipient']) : null;
        }
        if (array_key_exists('declared_sale_amount', $data)) {
            $row['declared_sale_amount'] = $data['declared_sale_amount'];
        }
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName('myca_dropship_quote'),
            $row,
            array_values(array_diff(array_keys($row), ['quote_id']))
        );
    }

    public function delete(int $quoteId): void
    {
        $this->resource->getConnection()->delete($this->resource->getTableName('myca_dropship_quote'), ['quote_id = ?' => $quoteId]);
    }
}

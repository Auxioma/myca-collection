<?php

declare(strict_types=1);

namespace Myca\Passport\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Lots, passeports, corrections tracées, révocation et remplacement, consultations.
 * Aucun passeport n'est supprimé : un ancien QR mène toujours à une page qui dit ce qu'il en est.
 */
class PassportManagement
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly PassportToken $token,
        private readonly LotFields $lotFields,
        private readonly Json $json,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EncryptorInterface $encryptor,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Crée un lot, ou le corrige : chaque valeur ou statut modifié est tracé avec son motif.
     *
     * @param array<string, string> $data
     * @param array<string, string> $statuses
     * @throws LocalizedException
     */
    public function saveLot(?int $lotId, array $data, array $statuses, string $reason, string $author): int
    {
        $values = [];
        foreach (array_keys($this->lotFields->getFields()) as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $values[$field] = $value === '' ? null : mb_substr($value, 0, 255);
        }
        if ($values['manufactured_on'] !== null && !preg_match('/^\d{4}-\d{2}$/', $values['manufactured_on'])) {
            throw new LocalizedException(__('Date de fabrication : utilisez le format AAAA-MM.'));
        }
        $cleanStatuses = [];
        foreach (array_keys($this->lotFields->getFields()) as $field) {
            $cleanStatuses[$field] = $this->lotFields->getStatus($statuses, $field);
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            if ($lotId === null) {
                $code = strtoupper(trim((string) ($data['lot_code'] ?? '')));
                if (!preg_match('/^[A-Z0-9][A-Z0-9-]{2,62}$/', $code)) {
                    throw new LocalizedException(__('Code de lot : lettres, chiffres et tirets, par exemple LOT-2026-10-014.'));
                }
                $productId = (int) ($data['product_id'] ?? 0);
                $this->getProduct($productId);
                if ($this->getLotByCode($code) !== null) {
                    throw new LocalizedException(__('Le lot %1 existe déjà.', $code));
                }
                $connection->insert($this->table('myca_passport_lot'), $values + [
                    'lot_code' => $code,
                    'product_id' => $productId,
                    'field_status' => $this->json->serialize($cleanStatuses),
                ]);
                $lotId = (int) $connection->fetchOne(
                    $connection->select()->from($this->table('myca_passport_lot'), ['lot_id'])->where('lot_code = ?', $code)
                );
                $this->addHistory($lotId, null, 'create', null, null, $code, null, $author);
            } else {
                $lot = $this->getLot($lotId);
                if ($lot === null) {
                    throw new LocalizedException(__('Ce lot est introuvable.'));
                }
                $oldStatuses = $this->getStatuses($lot);
                $changes = [];
                foreach ($values as $field => $value) {
                    $old = $lot[$field] === null ? null : (string) $lot[$field];
                    $oldStatus = $this->lotFields->getStatus($oldStatuses, $field);
                    if ($old !== $value || $oldStatus !== $cleanStatuses[$field]) {
                        $changes[$field] = [
                            ($old ?? '—') . ' (' . $oldStatus . ')',
                            ($value ?? '—') . ' (' . $cleanStatuses[$field] . ')',
                        ];
                    }
                }
                if (!$changes) {
                    $connection->commit();

                    return $lotId;
                }
                if (trim($reason) === '') {
                    throw new LocalizedException(__('Indiquez le motif de la correction : elle est tracée.'));
                }
                $connection->update(
                    $this->table('myca_passport_lot'),
                    $values + ['field_status' => $this->json->serialize($cleanStatuses)],
                    ['lot_id = ?' => $lotId]
                );
                foreach ($changes as $field => [$old, $new]) {
                    $this->addHistory($lotId, null, 'correct', $field, $old, $new, $reason, $author);
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $lotId;
    }

    /**
     * Un passeport par variante du modèle (taille, couleur). Les variantes qui en ont déjà un actif sont ignorées.
     *
     * @throws LocalizedException
     */
    public function generateForVariants(int $lotId, string $author): int
    {
        $lot = $this->getLot($lotId) ?? throw new LocalizedException(__('Ce lot est introuvable.'));
        $product = $this->getProduct((int) $lot['product_id']);
        $variants = [];
        $type = $product->getTypeInstance();
        if ($type instanceof Configurable) {
            foreach ($type->getUsedProducts($product) as $child) {
                $variants[(string) $child->getSku()] = (string) $child->getName();
            }
        } else {
            $variants[(string) $product->getSku()] = (string) $product->getName();
        }
        $connection = $this->connection();
        $existing = $connection->fetchCol(
            $connection->select()->from($this->table('myca_passport'), ['sku'])
                ->where('lot_id = ?', $lotId)->where('status = ?', self::STATUS_ACTIVE)->where('serial_number IS NULL')
        );
        $created = 0;
        foreach ($variants as $sku => $name) {
            if (in_array($sku, $existing, true)) {
                continue;
            }
            $passportId = $this->insertPassport($lotId, $sku, $name, null);
            $this->addHistory($lotId, $passportId, 'generate', null, null, $sku, null, $author);
            $created++;
        }

        return $created;
    }

    /**
     * Passeports à l'unité, numérotés LOT-…-0001, pour une variante du lot.
     *
     * @throws LocalizedException
     */
    public function generateSerials(int $lotId, string $sku, int $count, string $author): int
    {
        $lot = $this->getLot($lotId) ?? throw new LocalizedException(__('Ce lot est introuvable.'));
        if ($count < 1 || $count > 1000) {
            throw new LocalizedException(__('Indiquez entre 1 et 1 000 passeports numérotés.'));
        }
        $product = $this->getProduct((int) $lot['product_id']);
        $type = $product->getTypeInstance();
        $label = null;
        foreach ($type instanceof Configurable ? $type->getUsedProducts($product) : [$product] as $variant) {
            if ((string) $variant->getSku() === $sku) {
                $label = (string) $variant->getName();
            }
        }
        if ($label === null) {
            throw new LocalizedException(__('Cette variante n’appartient pas au modèle du lot.'));
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $last = (int) $connection->fetchOne(
                $connection->select()->from($this->table('myca_passport'), ['COUNT(*)'])
                    ->where('lot_id = ?', $lotId)->where('serial_number IS NOT NULL')->where('replaced_by_id IS NULL')
            );
            for ($i = 1; $i <= $count; $i++) {
                $serial = sprintf('%s-%04d', $lot['lot_code'], $last + $i);
                $passportId = $this->insertPassport($lotId, $sku, $label, $serial);
                $this->addHistory($lotId, $passportId, 'generate', null, null, $serial, null, $author);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $count;
    }

    /**
     * @throws LocalizedException
     */
    public function revoke(int $passportId, string $reason, string $author, ?int $replacedById = null): void
    {
        $passport = $this->getPassport($passportId) ?? throw new LocalizedException(__('Ce passeport est introuvable.'));
        if ($passport['status'] !== self::STATUS_ACTIVE) {
            throw new LocalizedException(__('Ce passeport est déjà révoqué.'));
        }
        if (trim($reason) === '') {
            throw new LocalizedException(__('Indiquez le motif de la révocation : il est affiché au public.'));
        }
        $this->connection()->update($this->table('myca_passport'), [
            'status' => self::STATUS_REVOKED,
            'revoked_reason' => mb_substr(trim($reason), 0, 255),
            'revoked_at' => gmdate('Y-m-d H:i:s'),
            'replaced_by_id' => $replacedById,
        ], ['passport_id = ?' => $passportId]);
        $this->addHistory((int) $passport['lot_id'], $passportId, $replacedById ? 'replace' : 'revoke', null, null, null, $reason, $author);
    }

    /**
     * Remplace un passeport (étiquette abîmée, erreur) : nouvel identifiant, l'ancien QR mène au nouveau.
     *
     * @throws LocalizedException
     */
    public function replace(int $passportId, string $reason, string $author): int
    {
        $passport = $this->getPassport($passportId) ?? throw new LocalizedException(__('Ce passeport est introuvable.'));
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $newId = $this->insertPassport(
                (int) $passport['lot_id'],
                (string) $passport['sku'],
                (string) $passport['variant_label'],
                $passport['serial_number'] === null ? null : (string) $passport['serial_number']
            );
            $this->revoke($passportId, $reason, $author, $newId);
            $this->addHistory((int) $passport['lot_id'], $newId, 'generate', null, null, (string) $passport['sku'], $reason, $author);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $newId;
    }

    /**
     * Passeport d'un identifiant (saisi ou scanné), quel que soit son statut.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $input): ?array
    {
        $token = $this->token->normalize($input);
        if (!$this->token->isValid($token)) {
            return null;
        }
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table('myca_passport'))->where('token = ?', $token));

        return is_array($row) ? $row : null;
    }

    /**
     * Enregistre une consultation sans conserver l'adresse IP (empreinte HMAC avec la clé de l'installation).
     */
    public function recordScan(int $passportId, string $ip): void
    {
        $this->connection()->insert($this->table('myca_passport_scan'), [
            'passport_id' => $passportId,
            'visitor_hash' => hash_hmac('sha256', $ip, $this->encryptor->hash('myca_passport_scan')),
        ]);
    }

    /**
     * @return array{visitors_24h: int, total: int, suspicious: bool}
     */
    public function getScanStats(int $passportId): array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->table('myca_passport_scan'), [
                    'total' => new \Zend_Db_Expr('COUNT(*)'),
                    'visitors_24h' => new \Zend_Db_Expr(sprintf(
                        "COUNT(DISTINCT IF(created_at >= '%s', visitor_hash, NULL))",
                        gmdate('Y-m-d H:i:s', time() - 86400)
                    )),
                ])
                ->where('passport_id = ?', $passportId)
        );
        $visitors = (int) ($row['visitors_24h'] ?? 0);
        $threshold = max(1, (int) $this->scopeConfig->getValue('myca_passport/general/scan_alert_threshold'));

        return ['visitors_24h' => $visitors, 'total' => (int) ($row['total'] ?? 0), 'suspicious' => $visitors > $threshold];
    }

    /**
     * « Ajouter à mes passeports » : rattache un passeport au compte du client (une seule fois).
     *
     * @throws LocalizedException
     */
    public function claim(int $customerId, string $input): string
    {
        $passport = $this->resolve($input);
        if ($passport === null) {
            throw new LocalizedException(__('Ce code de passeport est inconnu. Vérifiez-le sur l’étiquette.'));
        }
        $this->connection()->insertOnDuplicate(
            $this->table('myca_passport_owner'),
            ['passport_id' => (int) $passport['passport_id'], 'customer_id' => $customerId],
            ['passport_id']
        );

        return (string) $passport['token'];
    }

    public function release(int $customerId, int $passportId): void
    {
        $this->connection()->delete($this->table('myca_passport_owner'), ['passport_id = ?' => $passportId, 'customer_id = ?' => $customerId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOwned(int $customerId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()
                ->from(['owner' => $this->table('myca_passport_owner')], ['saved_at' => 'created_at'])
                ->join(['passport' => $this->table('myca_passport')], 'passport.passport_id = owner.passport_id')
                ->join(['lot' => $this->table('myca_passport_lot')], 'lot.lot_id = passport.lot_id', ['lot_code', 'product_id', 'collection'])
                ->where('owner.customer_id = ?', $customerId)
                ->order('owner.created_at DESC')
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLot(int $lotId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table('myca_passport_lot'))->where('lot_id = ?', $lotId));

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPassport(int $passportId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table('myca_passport'))->where('passport_id = ?', $passportId));

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPassports(int $lotId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->table('myca_passport'))->where('lot_id = ?', $lotId)->order(['sku', 'passport_id'])
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $lotId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->table('myca_passport_history'))->where('lot_id = ?', $lotId)->order('history_id DESC')
        );
    }

    /**
     * @param array<string, mixed> $lot
     * @return array<string, string>
     */
    public function getStatuses(array $lot): array
    {
        $statuses = $this->json->unserialize((string) ($lot['field_status'] ?? '{}'));

        return is_array($statuses) ? array_map('strval', $statuses) : [];
    }

    /**
     * @throws LocalizedException
     */
    public function getProduct(int $productId): Product
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            throw new LocalizedException(__('Ce produit est introuvable.'));
        }
        if (!$product instanceof Product) {
            throw new LocalizedException(__('Ce produit est introuvable.'));
        }

        return $product;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getLotByCode(string $code): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table('myca_passport_lot'))->where('lot_code = ?', $code));

        return is_array($row) ? $row : null;
    }

    private function insertPassport(int $lotId, string $sku, string $label, ?string $serial): int
    {
        $connection = $this->connection();
        $token = $this->token->generate();
        $connection->insert($this->table('myca_passport'), [
            'token' => $token,
            'lot_id' => $lotId,
            'sku' => $sku,
            'variant_label' => mb_substr($label, 0, 255),
            'serial_number' => $serial,
        ]);

        return (int) $connection->fetchOne(
            $connection->select()->from($this->table('myca_passport'), ['passport_id'])->where('token = ?', $token)
        );
    }

    private function addHistory(
        int $lotId,
        ?int $passportId,
        string $action,
        ?string $field,
        ?string $old,
        ?string $new,
        ?string $reason,
        string $author
    ): void {
        $this->connection()->insert($this->table('myca_passport_history'), [
            'lot_id' => $lotId,
            'passport_id' => $passportId,
            'action' => $action,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'reason' => $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 255),
            'author' => $author,
        ]);
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

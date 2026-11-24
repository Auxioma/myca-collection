<?php

declare(strict_types=1);

namespace Myca\Production\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Myca\Production\Model\Source\Status;

/**
 * Seul point d'écriture de la production. Garde-fous :
 * - les quantités sont prévisionnelles jusqu'à l'engagement, puis figées ;
 * - un ordre ne s'engage qu'avec des quantités, un prix usine, des tolérances validées et le MOQ respecté ;
 * - une réception n'entre en stock vendable que pour la décision « accepté » ; quarantaine et refus restent hors
 *   stock (vente bloquée) jusqu'à la levée de quarantaine ;
 * - fournisseur, lot, date, contrôleur et action corrective sont conservés ; chaque action est historisée.
 */
class ProductionManagement
{
    public const SOURCE_CODE = 'default';
    public const PHOTO_DIR = 'myca_production';
    private const MAX_PHOTOS = 5;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductionRules $rules,
        private readonly Json $json,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly GetSourceItemsBySkuInterface $getSourceItemsBySku,
        private readonly SourceItemsSaveInterface $sourceItemsSave,
        private readonly SourceItemInterfaceFactory $sourceItemFactory,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function saveSupplier(?int $supplierId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new LocalizedException(__('Indiquez le nom du fournisseur.'));
        }
        $email = trim((string) ($data['contact_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('L’e-mail du fournisseur est invalide.'));
        }
        $country = strtoupper(trim((string) ($data['country'] ?? '')));
        if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
            throw new LocalizedException(__('Pays : code à deux lettres (FR, PT, IT…).'));
        }
        $row = [
            'name' => mb_substr($name, 0, 255),
            'factory' => trim((string) ($data['factory'] ?? '')) ?: null,
            'country' => $country ?: null,
            'contact_email' => $email ?: null,
            'default_moq' => max(0, (int) ($data['default_moq'] ?? 0)),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'is_active' => empty($data['is_active']) ? 0 : 1,
        ];
        $connection = $this->connection();
        if ($supplierId === null) {
            return $this->insert('myca_production_supplier', $row);
        }
        $connection->update($this->table('myca_production_supplier'), $row, ['supplier_id = ?' => $supplierId]);

        return $supplierId;
    }

    /**
     * Nouvel ordre (prévisionnel) : une ligne par variante du modèle, quantités à zéro, six jalons à valider.
     *
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function createOrder(array $data, string $author): int
    {
        $supplier = $this->getSupplier((int) ($data['supplier_id'] ?? 0));
        if ($supplier === null || !(int) $supplier['is_active']) {
            throw new LocalizedException(__('Choisissez un fournisseur actif.'));
        }
        $product = $this->getProduct((int) ($data['product_id'] ?? 0));
        $variants = $this->getVariants($product);
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $orderId = $this->insert('myca_production_order', [
                'supplier_id' => (int) $supplier['supplier_id'],
                'product_id' => (int) $product->getId(),
                'model_name' => (string) $product->getName(),
                'season' => trim((string) ($data['season'] ?? '')) ?: null,
                'moq_per_color' => (int) $supplier['default_moq'],
            ]);
            $connection->update(
                $this->table('myca_production_order'),
                ['increment_id' => sprintf('PRO-%s-%04d', date('Y'), $orderId)],
                ['order_id = ?' => $orderId]
            );
            foreach ($variants as $variant) {
                $connection->insert($this->table('myca_production_line'), ['order_id' => $orderId] + $variant);
            }
            foreach (ProductionRules::MILESTONES as $code) {
                $connection->insert($this->table('myca_production_milestone'), ['order_id' => $orderId, 'code' => $code]);
            }
            $this->addHistory($orderId, 'create', (string) __('Ordre prévisionnel créé (%1 variantes).', count($variants)), $author);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $orderId;
    }

    /**
     * Informations générales et coûts. Quantités, MOQ et prix usine ne changent plus après l'engagement.
     *
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $quantities qty_planned par line_id
     * @throws LocalizedException
     */
    public function updateOrder(int $orderId, array $data, array $quantities, string $author): void
    {
        $order = $this->getOrderOrFail($orderId);
        if (in_array($order['status'], [ProductionRules::RECEIVED, ProductionRules::CANCELED], true)) {
            throw new LocalizedException(__('Cet ordre est clos.'));
        }
        // Seuls les champs envoyés sont modifiés : un appel partiel n'efface rien.
        $row = [];
        if (array_key_exists('planned_delivery', $data)) {
            $delivery = trim((string) $data['planned_delivery']);
            if ($delivery !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $delivery)) {
                throw new LocalizedException(__('Date de livraison prévue : format AAAA-MM-JJ.'));
            }
            $row['planned_delivery'] = $delivery ?: null;
        }
        foreach (['season', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $row[$field] = trim((string) $data[$field]) ?: null;
            }
        }
        if (array_key_exists('extra_costs', $data)) {
            $row['extra_costs'] = $this->amount($data['extra_costs']);
        }
        $editable = $this->rules->isEditable((string) $order['status']);
        if ($editable && array_key_exists('moq_per_color', $data)) {
            $row['moq_per_color'] = max(0, (int) $data['moq_per_color']);
        }
        if ($editable && array_key_exists('unit_factory_price', $data)) {
            $row['unit_factory_price'] = $this->amount($data['unit_factory_price']);
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            if ($row) {
                $connection->update($this->table('myca_production_order'), $row, ['order_id = ?' => $orderId]);
            }
            if ($editable) {
                foreach ($this->getLines($orderId) as $line) {
                    $qty = $quantities[(int) $line['line_id']] ?? null;
                    if ($qty === null) {
                        continue;
                    }
                    if (!is_numeric($qty) || (int) $qty < 0 || (int) $qty != $qty) {
                        throw new LocalizedException(__('Quantité invalide pour %1.', $line['sku']));
                    }
                    $connection->update($this->table('myca_production_line'), ['qty_planned' => (int) $qty], ['line_id = ?' => (int) $line['line_id']]);
                }
            }
            $this->addHistory($orderId, 'update', (string) __('Informations mises à jour.'), $author);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Tolérance dimensionnelle et seuils de défauts critiques / majeurs / mineurs, validés avant fabrication.
     *
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function validateTolerances(int $orderId, array $data, string $author): void
    {
        $order = $this->getOrderOrFail($orderId);
        if (!$this->rules->isEditable((string) $order['status'])) {
            throw new LocalizedException(__('Les tolérances sont figées depuis l’engagement de l’ordre.'));
        }
        $tolerance = str_replace(',', '.', trim((string) ($data['tolerance_cm'] ?? '')));
        if (!is_numeric($tolerance) || (float) $tolerance < 0 || (float) $tolerance > 10) {
            throw new LocalizedException(__('Tolérance dimensionnelle : entre 0 et 10 cm.'));
        }
        $limits = [];
        foreach (['max_critical', 'max_major', 'max_minor'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if (!ctype_digit($value)) {
                throw new LocalizedException(__('Indiquez chaque seuil de défauts (nombre entier, 0 accepté).'));
            }
            $limits[$field] = (int) $value;
        }
        $this->connection()->update($this->table('myca_production_order'), $limits + [
            'tolerance_cm' => (float) $tolerance,
            'tolerances_validated_at' => gmdate('Y-m-d H:i:s'),
            'tolerances_validated_by' => $author,
        ], ['order_id = ?' => $orderId]);
        $this->addHistory($orderId, 'tolerances', (string) __(
            'Tolérances validées : ±%1 cm ; défauts max. critiques %2, majeurs %3, mineurs %4.',
            (float) $tolerance,
            $limits['max_critical'],
            $limits['max_major'],
            $limits['max_minor']
        ), $author);
    }

    /**
     * @throws LocalizedException
     */
    public function setMilestone(int $orderId, string $code, string $status, string $note, string $author): void
    {
        if (!in_array($code, ProductionRules::MILESTONES, true) || !in_array($status, ['pending', 'validated', 'rejected'], true)) {
            throw new LocalizedException(__('Jalon inconnu.'));
        }
        $this->getOrderOrFail($orderId);
        $this->connection()->update($this->table('myca_production_milestone'), [
            'status' => $status,
            'note' => trim($note) ?: null,
            'decided_by' => $status === 'pending' ? null : $author,
            'decided_at' => $status === 'pending' ? null : gmdate('Y-m-d H:i:s'),
        ], ['order_id = ?' => $orderId, 'code = ?' => $code]);
        $statusLabel = ['pending' => __('à valider'), 'validated' => __('validé'), 'rejected' => __('refusé')][$status];
        $this->addHistory($orderId, 'milestone', $code . ' : ' . $statusLabel . (trim($note) === '' ? '' : ' — ' . trim($note)), $author);
    }

    /**
     * @throws LocalizedException
     */
    public function changeStatus(int $orderId, string $status, string $author, string $message = ''): void
    {
        $order = $this->getOrderOrFail($orderId);
        if (!$this->rules->canTransition((string) $order['status'], $status)) {
            throw new LocalizedException(__('Ce changement de statut n’est pas possible.'));
        }
        if ($status === ProductionRules::COMMITTED) {
            $blockers = $this->rules->getCommitBlockers($order, $this->getLines($orderId));
            if ($blockers) {
                throw new LocalizedException(__('Engagement impossible : %1', implode(' ', $blockers)));
            }
        }
        if ($status === ProductionRules::CANCELED && trim($message) === '') {
            throw new LocalizedException(__('Indiquez le motif de l’annulation.'));
        }
        $this->connection()->update($this->table('myca_production_order'), ['status' => $status], ['order_id = ?' => $orderId]);
        $this->addHistory($orderId, 'status', (Status::getLabels()[$status] ?? $status) . (trim($message) === '' ? '' : ' : ' . trim($message)), $author);
    }

    /**
     * Réception (partielle ou totale) et contrôle qualité. La décision s'applique à toutes les pièces reçues.
     *
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $quantities pièces reçues par line_id
     * @throws LocalizedException
     */
    public function recordReceipt(int $orderId, array $data, array $quantities, string $author): int
    {
        $order = $this->getOrderOrFail($orderId);
        if (!$this->rules->canReceive((string) $order['status'])) {
            throw new LocalizedException(__('La réception n’est possible qu’une fois l’ordre en fabrication.'));
        }
        $lotCode = strtoupper(trim((string) ($data['lot_code'] ?? '')));
        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{2,62}$/', $lotCode)) {
            throw new LocalizedException(__('Indiquez le numéro de lot (lettres, chiffres, tirets).'));
        }
        $receivedOn = trim((string) ($data['received_on'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedOn)) {
            throw new LocalizedException(__('Date de réception : format AAAA-MM-JJ.'));
        }
        $controller = trim((string) ($data['controller'] ?? ''));
        if ($controller === '') {
            throw new LocalizedException(__('Indiquez le nom du contrôleur qualité.'));
        }
        $lines = [];
        $total = 0;
        foreach ($this->getLines($orderId) as $line) {
            $qty = $quantities[(int) $line['line_id']] ?? 0;
            if (!is_numeric($qty) || (int) $qty < 0 || (int) $qty != $qty) {
                throw new LocalizedException(__('Quantité reçue invalide pour %1.', $line['sku']));
            }
            if ((int) $qty > 0) {
                $lines[(int) $line['line_id']] = ['qty' => (int) $qty, 'sku' => (string) $line['sku']];
                $total += (int) $qty;
            }
        }
        if ($total === 0) {
            throw new LocalizedException(__('Indiquez au moins une quantité reçue.'));
        }
        $checklist = [];
        foreach (ProductionRules::CHECKLIST as $point) {
            $checklist[$point] = ($data['checklist'][$point] ?? '') === 'ok' ? 'ok' : 'nok';
        }
        $defects = [];
        foreach (['critical', 'major', 'minor'] as $level) {
            $defects[$level] = max(0, (int) ($data['defects'][$level] ?? 0));
        }
        $sample = (int) ($data['sample_size'] ?? 0);
        if ($sample < 1 || $sample > $total) {
            throw new LocalizedException(__('Échantillon contrôlé : entre 1 et %1 pièces.', $total));
        }
        $decision = (string) ($data['decision'] ?? '');
        $limits = [
            'critical' => (int) $order['max_critical'],
            'major' => (int) $order['max_major'],
            'minor' => (int) $order['max_minor'],
        ];
        if (!in_array($decision, $this->rules->getAllowedDecisions($checklist, $defects, $limits), true)) {
            throw new LocalizedException(__('Décision incompatible avec le contrôle : checklist non conforme ou défauts au-delà des seuils validés.'));
        }
        $corrective = trim((string) ($data['corrective_action'] ?? ''));
        if ($decision !== ProductionRules::ACCEPTED && $corrective === '') {
            throw new LocalizedException(__('Indiquez l’action corrective demandée au fournisseur.'));
        }

        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $receiptId = $this->insert('myca_production_receipt', [
                'order_id' => $orderId,
                'lot_code' => $lotCode,
                'received_on' => $receivedOn,
                'controller' => mb_substr($controller, 0, 255),
                'checklist' => $this->json->serialize($checklist),
                'sample_size' => $sample,
                'defects_critical' => $defects['critical'],
                'defects_major' => $defects['major'],
                'defects_minor' => $defects['minor'],
                'decision' => $decision,
                'report' => trim((string) ($data['report'] ?? '')) ?: null,
                'corrective_action' => $corrective ?: null,
            ]);
            $column = ['accepted' => 'qty_accepted', 'quarantine' => 'qty_quarantine', 'refused' => 'qty_refused'][$decision];
            foreach ($lines as $lineId => $line) {
                $connection->insert($this->table('myca_production_receipt_line'), [
                    'receipt_id' => $receiptId,
                    'line_id' => $lineId,
                    'qty_received' => $line['qty'],
                    'qty_in_stock' => $decision === ProductionRules::ACCEPTED ? $line['qty'] : 0,
                ]);
                $connection->update($this->table('myca_production_line'), [
                    'qty_received' => new \Zend_Db_Expr('qty_received + ' . $line['qty']),
                    $column => new \Zend_Db_Expr($column . ' + ' . $line['qty']),
                ], ['line_id = ?' => $lineId]);
                if ($decision === ProductionRules::ACCEPTED) {
                    $this->addStock($line['sku'], $line['qty']);
                }
            }
            if ($order['status'] === ProductionRules::IN_PRODUCTION) {
                $connection->update($this->table('myca_production_order'), ['status' => ProductionRules::RECEIVING], ['order_id = ?' => $orderId]);
            }
            $this->addHistory($orderId, 'receipt', (string) __('Réception du lot %1 : %2 pièces, décision « %3 » (contrôleur : %4).', $lotCode, $total, $this->decisionLabel($decision), $controller), $author);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $receiptId;
    }

    /**
     * Photos du contrôle, conservées hors du dossier public (var/myca_production/<réception>/).
     *
     * @param string[] $fileIds identifiants des champs fichier envoyés
     * @throws LocalizedException
     */
    public function attachPhotos(int $receiptId, array $fileIds): int
    {
        $saved = [];
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $target = $directory->getAbsolutePath(self::PHOTO_DIR . '/' . $receiptId);
        foreach (array_slice($fileIds, 0, self::MAX_PHOTOS) as $fileId) {
            try {
                $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
            } catch (\Exception) {
                continue;
            }
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'png', 'webp']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);
            $result = $uploader->save($target);
            if (is_array($result) && !empty($result['file'])) {
                $saved[] = (string) $result['file'];
            }
        }
        if ($saved) {
            $existing = $this->getPhotos($receiptId);
            $this->connection()->update(
                $this->table('myca_production_receipt'),
                ['photos' => $this->json->serialize(array_values(array_unique([...$existing, ...$saved])))],
                ['receipt_id = ?' => $receiptId]
            );
        }

        return count($saved);
    }

    /**
     * Levée de quarantaine après nouveau contrôle : acceptées (entrée en stock vendable) ou refusées.
     *
     * @throws LocalizedException
     */
    public function releaseQuarantine(int $receiptId, string $decision, string $note, string $author): void
    {
        $receipt = $this->getReceipt($receiptId);
        if ($receipt === null || $receipt['decision'] !== ProductionRules::QUARANTINE) {
            throw new LocalizedException(__('Cette réception n’est pas en quarantaine.'));
        }
        if (!in_array($decision, [ProductionRules::ACCEPTED, ProductionRules::REFUSED], true) || trim($note) === '') {
            throw new LocalizedException(__('Choisissez la décision et indiquez le résultat du nouveau contrôle.'));
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $column = $decision === ProductionRules::ACCEPTED ? 'qty_accepted' : 'qty_refused';
            foreach ($this->getReceiptLines($receiptId) as $line) {
                $qty = (int) $line['qty_received'];
                $connection->update($this->table('myca_production_line'), [
                    'qty_quarantine' => new \Zend_Db_Expr('GREATEST(qty_quarantine - ' . $qty . ', 0)'),
                    $column => new \Zend_Db_Expr($column . ' + ' . $qty),
                ], ['line_id = ?' => (int) $line['line_id']]);
                if ($decision === ProductionRules::ACCEPTED) {
                    $this->addStock((string) $line['sku'], $qty);
                    $connection->update(
                        $this->table('myca_production_receipt_line'),
                        ['qty_in_stock' => $qty],
                        ['receipt_id = ?' => $receiptId, 'line_id = ?' => (int) $line['line_id']]
                    );
                }
            }
            $connection->update($this->table('myca_production_receipt'), [
                'decision' => $decision,
                'corrective_action' => trim((string) $receipt['corrective_action'] . "\n" . __('Levée de quarantaine : %1', trim($note))),
            ], ['receipt_id = ?' => $receiptId]);
            $this->addHistory((int) $receipt['order_id'], 'quarantine', (string) __('Lot %1 : quarantaine levée, « %2 ». %3', $receipt['lot_code'], $this->decisionLabel($decision), trim($note)), $author);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrder(int $orderId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table('myca_production_order'))->where('order_id = ?', $orderId));

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLines(int $orderId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->table('myca_production_line'))->where('order_id = ?', $orderId)->order(['color', 'line_id'])
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMilestones(int $orderId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->table('myca_production_milestone'))->where('order_id = ?', $orderId)->order('milestone_id')
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReceipts(int $orderId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->table('myca_production_receipt'))->where('order_id = ?', $orderId)->order('receipt_id DESC')
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getReceipt(int $receiptId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table('myca_production_receipt'))->where('receipt_id = ?', $receiptId));

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReceiptLines(int $receiptId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()
                ->from(['rl' => $this->table('myca_production_receipt_line')])
                ->join(['l' => $this->table('myca_production_line')], 'l.line_id = rl.line_id', ['sku', 'color', 'size'])
                ->where('rl.receipt_id = ?', $receiptId)
        );
    }

    /**
     * @return string[]
     */
    public function getPhotos(int $receiptId): array
    {
        $receipt = $this->getReceipt($receiptId);
        $photos = $receipt && $receipt['photos'] ? $this->json->unserialize((string) $receipt['photos']) : [];

        return is_array($photos) ? array_map('strval', $photos) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $orderId): array
    {
        $connection = $this->connection();

        return $connection->fetchAll(
            $connection->select()->from($this->table('myca_production_history'))->where('order_id = ?', $orderId)->order('history_id DESC')
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSupplier(int $supplierId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table('myca_production_supplier'))->where('supplier_id = ?', $supplierId));

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, string>
     */
    public function getSupplierOptions(bool $activeOnly): array
    {
        $connection = $this->connection();
        $select = $connection->select()->from($this->table('myca_production_supplier'), ['supplier_id', 'name'])->order('name');
        if ($activeOnly) {
            $select->where('is_active = 1');
        }
        $options = [];
        foreach ($connection->fetchPairs($select) as $id => $name) {
            $options[(int) $id] = (string) $name;
        }

        return $options;
    }

    /**
     * @throws LocalizedException
     */
    public function getProduct(int $productId): Product
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Ce produit est introuvable.'));
        }
        if (!$product instanceof Product) {
            throw new LocalizedException(__('Ce produit est introuvable.'));
        }

        return $product;
    }

    /**
     * Ajoute des pièces acceptées au stock de la source principale (stock vendable).
     */
    private function addStock(string $sku, int $qty): void
    {
        $items = $this->getSourceItemsBySku->execute($sku);
        $item = null;
        foreach ($items as $candidate) {
            if ($candidate->getSourceCode() === self::SOURCE_CODE) {
                $item = $candidate;
            }
        }
        if ($item === null) {
            $item = $this->sourceItemFactory->create();
            $item->setSku($sku);
            $item->setSourceCode(self::SOURCE_CODE);
            $item->setQuantity(0);
        }
        $item->setQuantity((float) $item->getQuantity() + $qty);
        $item->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItemsSave->execute([$item]);
    }

    /**
     * Variantes du modèle, avec leurs libellés de couleur et de taille lus dans les attributs configurables du
     * produit (codes propres au catalogue : myca_color, myca_size…).
     *
     * @return array<int, array{sku: string, color: ?string, size: ?string}>
     */
    private function getVariants(Product $product): array
    {
        $type = $product->getTypeInstance();
        if (!$type instanceof Configurable) {
            return [['sku' => (string) $product->getSku(), 'color' => null, 'size' => null]];
        }
        $colorCode = null;
        $sizeCode = null;
        $labels = [];
        foreach ($type->getConfigurableAttributesAsArray($product) as $attribute) {
            $code = (string) $attribute['attribute_code'];
            if ($colorCode === null && preg_match('/colo|coul/i', $code)) {
                $colorCode = $code;
            } elseif ($sizeCode === null && preg_match('/size|taille/i', $code)) {
                $sizeCode = $code;
            }
            foreach ((array) ($attribute['values'] ?? []) as $value) {
                $labels[$code][(string) $value['value_index']] = (string) ($value['store_label'] ?? $value['label'] ?? '');
            }
        }
        $variants = [];
        foreach ($type->getUsedProducts($product) as $child) {
            if (!$child instanceof Product) {
                continue;
            }
            $variants[] = [
                'sku' => (string) $child->getSku(),
                'color' => $colorCode ? ($labels[$colorCode][(string) $child->getData($colorCode)] ?? null) : null,
                'size' => $sizeCode ? ($labels[$sizeCode][(string) $child->getData($sizeCode)] ?? null) : null,
            ];
        }

        return $variants;
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function getOrderOrFail(int $orderId): array
    {
        return $this->getOrder($orderId) ?? throw new LocalizedException(__('Cet ordre de production est introuvable.'));
    }

    /**
     * @throws LocalizedException
     */
    private function amount(mixed $value): float
    {
        $value = str_replace([' ', ','], ['', '.'], trim((string) $value));
        if ($value === '') {
            return 0.0;
        }
        if (!is_numeric($value) || (float) $value < 0) {
            throw new LocalizedException(__('Montant invalide : %1.', $value));
        }

        return round((float) $value, 2);
    }

    private function decisionLabel(string $decision): string
    {
        return (string) ([
            ProductionRules::ACCEPTED => __('accepté'),
            ProductionRules::QUARANTINE => __('quarantaine'),
            ProductionRules::REFUSED => __('refusé'),
        ][$decision] ?? $decision);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): int
    {
        $connection = $this->connection();
        $connection->insert($this->table($table), $row);

        return $connection instanceof Mysql ? (int) $connection->lastInsertId($this->table($table)) : 0;
    }

    private function addHistory(int $orderId, string $action, string $message, string $author): void
    {
        $this->connection()->insert($this->table('myca_production_history'), [
            'order_id' => $orderId,
            'action' => $action,
            'message' => $message,
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

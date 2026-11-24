<?php

declare(strict_types=1);

namespace Myca\Production\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Fiche technique d'un modèle (M11) : composition en pourcentages (total 100 %), grammage, coupe, entretien,
 * grille de mesures par taille et tests qualité.
 */
class TechpackManagement
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductionRules $rules,
        private readonly Json $json,
        private readonly ProductionManagement $productionManagement
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function save(int $productId, array $data, string $author): void
    {
        $this->productionManagement->getProduct($productId);
        $composition = $this->rules->parseComposition((string) ($data['composition'] ?? ''));
        if ($composition === null) {
            throw new LocalizedException(__('Composition : « Coton=95, Élasthanne=5 », total de 100 %.'));
        }
        $sizeChart = $this->rules->parseSizeChart((string) ($data['size_chart'] ?? ''));
        if ($sizeChart === null) {
            throw new LocalizedException(__('Grille de mesures : une taille par ligne, « S; 96; 68; 42 » (mesures en cm).'));
        }
        $row = [
            'product_id' => $productId,
            'model_reference' => trim((string) ($data['model_reference'] ?? '')) ?: null,
            'composition' => $this->json->serialize($composition),
            'grammage' => trim((string) ($data['grammage'] ?? '')) ?: null,
            'cut' => trim((string) ($data['cut'] ?? '')) ?: null,
            'care' => trim((string) ($data['care'] ?? '')) ?: null,
            'size_chart' => $this->json->serialize($sizeChart),
            'quality_tests' => trim((string) ($data['quality_tests'] ?? '')) ?: null,
            'updated_by' => $author,
        ];
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName('myca_production_techpack'),
            $row,
            array_keys(array_diff_key($row, ['product_id' => true]))
        );
    }

    /**
     * Fiche prête à être éditée : composition et grille remises au format de saisie.
     *
     * @return array<string, string>
     */
    public function getForm(int $productId): array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->resource->getTableName('myca_production_techpack'))->where('product_id = ?', $productId)
        );
        if (!is_array($row)) {
            return [];
        }
        $composition = (array) $this->json->unserialize((string) $row['composition']);
        $chart = (array) $this->json->unserialize((string) $row['size_chart']);

        return [
            'model_reference' => (string) $row['model_reference'],
            'composition' => implode(', ', array_map(static fn ($name, $share) => $name . '=' . $share, array_keys($composition), $composition)),
            'grammage' => (string) $row['grammage'],
            'cut' => (string) $row['cut'],
            'care' => (string) $row['care'],
            'size_chart' => implode("\n", array_map(static fn ($size, $values) => $size . '; ' . implode('; ', (array) $values), array_keys($chart), $chart)),
            'quality_tests' => (string) $row['quality_tests'],
            'updated_by' => (string) $row['updated_by'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}

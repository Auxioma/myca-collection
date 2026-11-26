<?php

declare(strict_types=1);

namespace Myca\Catalog\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Myca\Catalog\Model\Source\CareInstruction;

/**
 * Données de l'onglet « Matières & entretien » de la fiche produit : seules les valeurs saisies sont rendues.
 */
class MaterialsCare implements ArgumentInterface
{
    /** attribut => [libellé, icône] */
    private const SPECS = [
        'myca_composition' => ['Composition', 'layers'],
        'myca_grammage' => ['Grammage', 'chart'],
        'myca_touch' => ['Toucher', 'feather'],
        'myca_breathability' => ['Respirabilité', 'leaf'],
        'myca_elasticity' => ['Élasticité', 'swap'],
        'myca_finishes' => ['Finitions', 'gem'],
    ];

    public function hasStructuredData(Product $product): bool
    {
        return $this->getFabric($product)['title'] !== '' || $this->getSpecs($product) !== []
            || $this->getCare($product) !== [];
    }

    /**
     * @return array{title: string, description: string}
     */
    public function getFabric(Product $product): array
    {
        return [
            'title' => $this->text($product, 'myca_fabric'),
            'description' => $this->text($product, 'myca_fabric_description'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, icon: string}>
     */
    public function getSpecs(Product $product): array
    {
        $specs = [];
        foreach (self::SPECS as $code => [$label, $icon]) {
            $value = $this->text($product, $code);
            if ($value !== '') {
                $specs[] = ['label' => (string) __($label), 'value' => $value, 'icon' => $icon];
            }
        }

        return $specs;
    }

    /**
     * Consignes cochées, dans l'ordre de la liste (lavage, javel, séchage, repassage, nettoyage).
     *
     * @return array<int, array{code: string, label: string, icon: string}>
     */
    public function getCare(Product $product): array
    {
        $selected = array_filter(explode(',', $this->text($product, 'myca_care')));
        $care = [];
        foreach (CareInstruction::OPTIONS as $code => $label) {
            if (in_array($code, $selected, true)) {
                $care[] = ['code' => $code, 'label' => (string) __($label), 'icon' => 'care-' . $code];
            }
        }

        return $care;
    }

    /**
     * @return string[]
     */
    public function getTips(Product $product): array
    {
        $lines = preg_split('/\R/', $this->text($product, 'myca_care_tips')) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    /**
     * Valeur saisie, avec espace insécable avant « % » (94 % polyester) pour éviter une coupure de ligne.
     */
    private function text(Product $product, string $code): string
    {
        return str_replace(' %', "\u{00A0}%", trim((string) $product->getData($code)));
    }
}

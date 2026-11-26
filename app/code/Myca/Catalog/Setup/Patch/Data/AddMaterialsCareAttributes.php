<?php

declare(strict_types=1);

namespace Myca\Catalog\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Myca\Catalog\Model\Source\CareInstruction;

/**
 * Onglet « Matières & entretien » structuré : tissu, composition, grammage, toucher, respirabilité, élasticité,
 * finitions, consignes d'entretien (symboles) et conseils MYCA. Chaque champ vide est simplement masqué ; le
 * texte libre « Matières & entretien » reste affiché tant qu'aucune donnée structurée n'est saisie.
 */
class AddMaterialsCareAttributes implements DataPatchInterface
{
    private const GROUP = 'MYCA – Matières & entretien';

    /** code => [libellé, saisie, type] */
    private const ATTRIBUTES = [
        'myca_fabric' => ['Tissu (titre)', 'text', 'varchar'],
        'myca_fabric_description' => ['Tissu (description)', 'textarea', 'text'],
        'myca_composition' => ['Composition', 'text', 'varchar'],
        'myca_grammage' => ['Grammage', 'text', 'varchar'],
        'myca_touch' => ['Toucher', 'text', 'varchar'],
        'myca_breathability' => ['Respirabilité', 'text', 'varchar'],
        'myca_elasticity' => ['Élasticité', 'text', 'varchar'],
        'myca_finishes' => ['Finitions', 'text', 'varchar'],
        'myca_care_tips' => ['Conseils MYCA (un par ligne)', 'textarea', 'text'],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $sortOrder = 10;
        foreach (self::ATTRIBUTES as $code => [$label, $input, $type]) {
            $eavSetup->addAttribute(Product::ENTITY, $code, [
                'type' => $type,
                'label' => $label,
                'input' => $input,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'required' => false,
                'user_defined' => true,
                'searchable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'wysiwyg_enabled' => false,
                'is_html_allowed_on_front' => false,
                'group' => self::GROUP,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
            if ($code === 'myca_finishes') {
                $eavSetup->addAttribute(Product::ENTITY, 'myca_care', [
                    'type' => 'varchar',
                    'label' => 'Consignes d’entretien',
                    'input' => 'multiselect',
                    'source' => CareInstruction::class,
                    'backend' => ArrayBackend::class,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'required' => false,
                    'user_defined' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'group' => self::GROUP,
                    'sort_order' => $sortOrder,
                ]);
                $sortOrder += 10;
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddProductAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}

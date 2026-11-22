<?php

declare(strict_types=1);

namespace Myca\Catalog\Setup\Patch\Data;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Attributs produit MYCA. Les valeurs de couleur reprennent les pastilles des maquettes P03/P04 ;
 * les tailles celles de la fiche M45.
 */
class AddProductAttributes implements DataPatchInterface
{
    private const GROUP = 'MYCA';

    private const COLLECTIONS = [
        'España – Hidalgo',
        'England – Westminster',
        'Grèce – Poseidon',
        'France – Lys',
        'Italia – La Plaza',
        'MYCA Signature',
    ];

    private const COLORS = [
        'Blanc' => '#ffffff',
        'Marine' => '#0f1b35',
        'Rouge' => '#d71920',
        'Bleu ciel' => '#93c5fd',
        'Vert' => '#16a34a',
        'Noir' => '#111827',
        'Gris' => '#6b7280',
    ];

    private const SIZES = ['S', 'M', 'L', 'XL', 'XXL', '3XL'];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly ProductAttributeRepositoryInterface $attributeRepository
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(Product::ENTITY, 'myca_collection', $this->selectAttribute('Collection', 10, [
            'option' => ['values' => self::COLLECTIONS],
            'used_for_sort_by' => false,
        ]));

        $eavSetup->addAttribute(Product::ENTITY, 'myca_best_seller', [
            'type' => 'int',
            'label' => 'Best-seller',
            'input' => 'boolean',
            'source' => Boolean::class,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'required' => false,
            'default' => '0',
            'user_defined' => true,
            'used_in_product_listing' => true,
            'visible_on_front' => false,
            'group' => self::GROUP,
            'sort_order' => 20,
        ]);

        $eavSetup->addAttribute(Product::ENTITY, 'myca_color', $this->selectAttribute('Couleur', 30));
        $eavSetup->addAttribute(Product::ENTITY, 'myca_size', $this->selectAttribute('Taille', 40));

        $eavSetup->addAttribute(Product::ENTITY, 'myca_tagline', $this->textAttribute('Accroche', 'text', 'varchar', 50));
        $eavSetup->addAttribute(
            Product::ENTITY,
            'myca_materials',
            $this->textAttribute('Matières & entretien', 'textarea', 'text', 60)
        );

        $this->saveSwatches('myca_color', 'visual', self::COLORS);
        $this->saveSwatches('myca_size', 'text', array_combine(self::SIZES, self::SIZES));

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function selectAttribute(string $label, int $sortOrder, array $overrides = []): array
    {
        return $overrides + [
            'type' => 'int',
            'label' => $label,
            'input' => 'select',
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'required' => false,
            'user_defined' => true,
            'searchable' => true,
            'filterable' => 1,
            'filterable_in_search' => true,
            'comparable' => false,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'is_used_in_grid' => true,
            'is_filterable_in_grid' => true,
            'group' => self::GROUP,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textAttribute(string $label, string $input, string $type, int $sortOrder): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'input' => $input,
            'global' => ScopedAttributeInterface::SCOPE_STORE,
            'required' => false,
            'user_defined' => true,
            'searchable' => $input === 'text',
            'wysiwyg_enabled' => $input === 'textarea',
            'is_html_allowed_on_front' => $input === 'textarea',
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'group' => self::GROUP,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Convertit une liste déroulante en pastilles ; le plugin Magento_Swatches crée les options.
     *
     * @param array<string, string> $values libellé => valeur de la pastille
     */
    private function saveSwatches(string $attributeCode, string $type, array $values): void
    {
        /** @var Attribute $attribute */
        $attribute = $this->attributeRepository->get($attributeCode);

        $options = ['value' => [], 'order' => []];
        $swatches = ['value' => []];
        $index = 0;
        foreach ($values as $label => $swatch) {
            $key = 'option_' . $index;
            $options['value'][$key] = [0 => $label];
            $options['order'][$key] = $index + 1;
            // Pastille texte : une valeur par vue de magasin ; pastille visuelle : une couleur.
            $swatches['value'][$key] = $type === 'text' ? [0 => $swatch] : $swatch;
            $index++;
        }

        $attribute->addData([
            'swatch_input_type' => $type,
            'update_product_preview_image' => $type === 'visual' ? 1 : 0,
            'use_product_image_for_swatch' => 0,
            'option' . $type => $options,
            'swatch' . $type => $swatches,
        ]);
        $this->attributeRepository->save($attribute);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}

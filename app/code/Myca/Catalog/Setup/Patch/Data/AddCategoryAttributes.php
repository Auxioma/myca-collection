<?php

declare(strict_types=1);

namespace Myca\Catalog\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Textes éditoriaux des collections (maquettes P01, P02, P03).
 */
class AddCategoryAttributes implements DataPatchInterface
{
    public const CARD_LABEL = 'myca_card_label';
    public const HERO_KICKER = 'myca_hero_kicker';
    public const HERO_TITLE = 'myca_hero_title';
    public const HERO_TEXT = 'myca_hero_text';
    public const TAGLINE = 'myca_tagline';
    public const IN_COLLECTIONS = 'myca_in_collections';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $texts = [
            self::CARD_LABEL => ['Étiquette de la carte (ex. « Capsule »)', 100],
            self::HERO_KICKER => ['Surtitre de la bannière (ex. « Capsule España »)', 110],
            self::HERO_TITLE => ['Titre de la bannière (ex. « Hidalgo »)', 112],
            self::HERO_TEXT => ['Texte de la bannière', 114],
            self::TAGLINE => ['Accroche courte', 120],
        ];
        foreach ($texts as $code => [$label, $sortOrder]) {
            $eavSetup->addAttribute(Category::ENTITY, $code, [
                'type' => 'varchar',
                'label' => $label,
                'input' => 'text',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'required' => false,
                'user_defined' => true,
                'group' => 'MYCA',
                'sort_order' => $sortOrder,
            ]);
        }

        $eavSetup->addAttribute(Category::ENTITY, self::IN_COLLECTIONS, [
            'type' => 'int',
            'label' => 'Afficher sur la page « Nos collections »',
            'input' => 'boolean',
            'source' => Boolean::class,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'required' => false,
            'default' => '0',
            'user_defined' => true,
            'group' => 'MYCA',
            'sort_order' => 130,
        ]);

        return $this;
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

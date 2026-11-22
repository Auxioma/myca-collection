<?php

declare(strict_types=1);

namespace Myca\Shipping\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Myca\Shipping\Model\FreeRelayThreshold;

/**
 * Attribut « Éligible au point relais offert » (oui par défaut) : seuls ces produits comptent pour le seuil.
 */
class AddRelayEligibleAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup])->addAttribute(
            Product::ENTITY,
            FreeRelayThreshold::ELIGIBLE_ATTRIBUTE,
            [
                'type' => 'int',
                'label' => 'Éligible au point relais offert',
                'input' => 'boolean',
                'source' => Boolean::class,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'required' => false,
                'default' => '1',
                'user_defined' => true,
                'used_in_product_listing' => false,
                'group' => 'MYCA',
                'sort_order' => 70,
            ]
        );

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

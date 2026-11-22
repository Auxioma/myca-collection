<?php

declare(strict_types=1);

namespace Myca\Catalog\Setup\Patch\Data;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Rend l'attribut « Best-seller » utilisable dans les conditions de l'élément Page Builder « Produits »
 * (section Best-sellers de l'accueil).
 */
class EnableBestSellerCondition implements DataPatchInterface
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository
    ) {
    }

    public function apply(): self
    {
        /** @var Attribute $attribute */
        $attribute = $this->attributeRepository->get('myca_best_seller');
        $attribute->setData('is_used_for_promo_rules', 1);
        $this->attributeRepository->save($attribute);

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

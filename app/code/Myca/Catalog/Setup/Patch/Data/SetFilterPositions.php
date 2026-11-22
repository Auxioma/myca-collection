<?php

declare(strict_types=1);

namespace Myca\Catalog\Setup\Patch\Data;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Ordre des filtres de la liste produits (maquette P04) : Collection, Taille, Couleur, Prix.
 */
class SetFilterPositions implements DataPatchInterface
{
    private const POSITIONS = [
        'myca_collection' => 10,
        'myca_size' => 20,
        'myca_color' => 30,
        'price' => 40,
    ];

    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository
    ) {
    }

    public function apply(): self
    {
        foreach (self::POSITIONS as $code => $position) {
            $attribute = $this->attributeRepository->get($code);
            $attribute->setPosition($position);
            $this->attributeRepository->save($attribute);
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

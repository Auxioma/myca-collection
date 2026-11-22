<?php

declare(strict_types=1);

namespace Myca\Catalog\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Arborescence du catalogue telle que présentée dans le menu et les pages P01–P04.
 * Les textes sont ceux des maquettes ; les images restent à fournir (photos HD).
 */
class CreateCategoryTree implements DataPatchInterface
{
    private const TREE = [
        [
            'name' => 'Collections',
            'url_key' => 'collections',
            'display_mode' => Category::DM_PAGE,
            'myca_hero_title' => 'Nos collections',
            'description' => 'Des capsules inspirées de nos voyages et une ligne permanente : MYCA Signature.',
            'children' => [
                [
                    'name' => 'España – Hidalgo',
                    'url_key' => 'espana-hidalgo',
                    'myca_card_label' => 'Capsule',
                    'myca_hero_kicker' => 'Capsule España',
                    'myca_hero_title' => 'Hidalgo',
                    'myca_hero_text' => 'Couleurs du drapeau, écussons brodés et maille piquée : '
                        . 'la fierté ibérique en version sport chic.',
                    'myca_tagline' => 'L’élégance ibérique, entre tradition et esprit sport chic.',
                    'description' => 'Inspirée des places de Séville et des façades andalouses, la capsule Hidalgo '
                        . 'associe le rouge et l’or à des coupes modernes. Chaque modèle porte un intérieur de col '
                        . 'imprimé exclusif et un écusson brodé.',
                ],
                [
                    'name' => 'England – Westminster',
                    'url_key' => 'england-westminster',
                    'myca_card_label' => 'Capsule',
                    'myca_tagline' => 'Le chic britannique revisité.',
                ],
                [
                    'name' => 'Grèce – Poseidon',
                    'url_key' => 'grece-poseidon',
                    'myca_card_label' => 'Capsule',
                    'myca_tagline' => 'L’azur méditerranéen, frais et lumineux.',
                ],
                [
                    'name' => 'France – Lys',
                    'url_key' => 'france-lys',
                    'myca_card_label' => 'Capsule',
                    'myca_tagline' => 'L’héritage français, raffiné et intemporel.',
                ],
                [
                    'name' => 'Italia – La Plaza',
                    'url_key' => 'italia-la-plaza',
                    'myca_card_label' => 'Capsule',
                    'myca_tagline' => 'La dolce vita en version sportswear.',
                ],
            ],
        ],
        [
            'name' => 'Polos',
            'url_key' => 'polos',
            'myca_tagline' => 'Maille piquée premium, coupes classiques et modernes',
        ],
        ['name' => 'Tee-shirts', 'url_key' => 'tee-shirts'],
        ['name' => 'Sweats', 'url_key' => 'sweats', 'include_in_menu' => 0],
        ['name' => 'Casquettes', 'url_key' => 'casquettes'],
        [
            'name' => 'MYCA Signature',
            'url_key' => 'myca-signature',
            'myca_card_label' => 'Ligne permanente',
            'myca_tagline' => 'Nos modèles iconiques, sélectionnés par MYCA.',
            'myca_in_collections' => 1,
        ],
    ];

    public function __construct(
        private readonly CategoryInterfaceFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        /** @var Store|null $store */
        $store = $this->storeManager->getDefaultStoreView();
        $rootId = (int) $store?->getRootCategoryId();
        $this->createChildren($rootId, self::TREE);

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private function createChildren(int $parentId, array $nodes): void
    {
        foreach ($nodes as $position => $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);

            $category = $this->findChild($parentId, (string) $node['url_key'])
                ?? $this->createCategory($parentId, $position + 1, $node);

            $this->createChildren((int) $category->getId(), $children);
        }
    }

    private function findChild(int $parentId, string $urlKey): ?CategoryInterface
    {
        /** @var Category|null $existing */
        $existing = $this->collectionFactory->create()
            ->addAttributeToFilter('parent_id', ['eq' => $parentId])
            ->addAttributeToFilter('url_key', ['eq' => $urlKey])
            ->getFirstItem();

        return $existing && $existing->getId() ? $existing : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCategory(int $parentId, int $position, array $data): CategoryInterface
    {
        /** @var Category $category */
        $category = $this->categoryFactory->create();
        $category->setStoreId(Store::DEFAULT_STORE_ID);
        $category->addData($data + [
            'is_active' => 1,
            'is_anchor' => 1,
            'include_in_menu' => 1,
            'display_mode' => Category::DM_PRODUCT,
        ]);
        $category->setParentId($parentId);
        $category->setPosition($position);

        return $this->categoryRepository->save($category);
    }

    public static function getDependencies(): array
    {
        return [AddCategoryAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}

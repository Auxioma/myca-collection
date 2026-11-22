<?php

declare(strict_types=1);

namespace Myca\Catalog\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Myca\Catalog\Setup\Patch\Data\AddCategoryAttributes;

/**
 * Cartes de catégories : collections (P01, P02) et accès par type de produit (P01).
 */
class CategoryCards implements ArgumentInterface
{
    private const CARD_ATTRIBUTES = [
        'name',
        'url_key',
        'url_path',
        'image',
        AddCategoryAttributes::CARD_LABEL,
        AddCategoryAttributes::TAGLINE,
    ];

    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility
    ) {
    }

    /**
     * Sous-catégories actives d'une catégorie, suivies des lignes marquées « Afficher sur la page
     * Nos collections » (ex. MYCA Signature, rangée à la racine pour figurer dans le menu).
     *
     * @return Category[]
     */
    public function getCollectionCards(Category $parent): array
    {
        $children = $this->createCollection()
            ->addAttributeToFilter('parent_id', ['eq' => (int) $parent->getId()])
            ->setOrder('position', 'ASC');

        $extras = $this->createCollection()
            ->addAttributeToFilter(AddCategoryAttributes::IN_COLLECTIONS, ['eq' => 1])
            ->addAttributeToFilter('parent_id', ['neq' => (int) $parent->getId()])
            ->setOrder('position', 'ASC');

        /** @var Category[] $items */
        $items = array_merge(array_values($children->getItems()), array_values($extras->getItems()));

        return $items;
    }

    /**
     * Catégories désignées par leur clé d'URL, dans l'ordre demandé.
     *
     * @param string[] $urlKeys
     * @return Category[]
     */
    public function getByUrlKeys(array $urlKeys): array
    {
        /** @var Category[] $items */
        $items = $this->createCollection()->addAttributeToFilter('url_key', ['in' => $urlKeys])->getItems();
        $byKey = [];
        foreach ($items as $category) {
            $byKey[(string) $category->getData('url_key')] = $category;
        }

        return array_values(array_filter(array_map(static fn ($key) => $byKey[$key] ?? null, $urlKeys)));
    }

    /**
     * Nombre de modèles visibles en vitrine (« 48 modèles »).
     */
    public function getProductCount(Category $category): int
    {
        return $this->productCollectionFactory->create()
            ->addCategoryFilter($category)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addAttributeToFilter('status', ['eq' => 1])
            ->getSize();
    }

    private function createCollection(): CategoryCollection
    {
        return $this->categoryCollectionFactory->create()
            ->setStoreId((int) $this->storeManager->getStore()->getId())
            ->addAttributeToSelect(self::CARD_ATTRIBUTES)
            ->addAttributeToFilter('is_active', ['eq' => 1])
            ->addUrlRewriteToResult();
    }
}

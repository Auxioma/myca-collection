<?php

declare(strict_types=1);

namespace Myca\Catalog\Model;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Search\Model\Query;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory as QueryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Aperçu de recherche du champ de l'en-tête : produits (moteur de recherche du catalogue), catégories et
 * recherches populaires correspondant à la saisie. Seuls des produits visibles en recherche et actifs remontent.
 */
class SearchPreview
{
    public function __construct(
        /** Fabrique de la collection de recherche plein texte (type virtuel, voir etc/frontend/di.xml) */
        private readonly ProductCollectionFactory $fulltextCollectionFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly QueryCollectionFactory $queryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @return array{products: array<int, array{name: string, url: string, image: string, price: string}>,
     *     categories: array<int, array{name: string, url: string}>, terms: string[]}
     */
    public function get(string $term, int $productLimit = 5): array
    {
        return [
            'products' => $this->getProducts($term, $productLimit),
            'categories' => $this->getCategories($term),
            'terms' => $this->getTerms($term),
        ];
    }

    /**
     * @return array<int, array{name: string, url: string, image: string, price: string}>
     */
    private function getProducts(string $term, int $limit): array
    {
        $collection = $this->fulltextCollectionFactory->create();
        if (!$collection instanceof FulltextCollection) {
            return [];
        }
        $collection->addSearchFilter($term)
            ->addAttributeToSelect(['name', 'small_image', 'thumbnail', 'url_key'])
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addUrlRewrite()
            ->setVisibility($this->visibility->getVisibleInSearchIds())
            ->setPageSize($limit)
            ->setCurPage(1);

        $found = $collection->getItems();
        // Mot en cours de frappe (« pol », « espa ») : le moteur cherche des mots entiers, on complète par le nom
        if (count($found) < $limit) {
            $partial = $this->productCollectionFactory->create()
                ->addAttributeToSelect(['name', 'small_image', 'thumbnail', 'url_key'])
                ->addAttributeToFilter('name', ['like' => '%' . $this->escapeLike($term) . '%'])
                ->addAttributeToFilter('status', ['eq' => 1])
                ->setVisibility($this->visibility->getVisibleInSearchIds())
                ->addStoreFilter()
                ->addMinimalPrice()
                ->addFinalPrice()
                ->addUrlRewrite()
                ->setPageSize($limit);
            foreach ($partial as $product) {
                $found[$product->getId()] ??= $product;
            }
        }

        $products = [];
        /** @var Product $product */
        foreach (array_slice($found, 0, $limit, true) as $product) {
            $price = (float) ($product->getData('min_price') ?: $product->getFinalPrice());
            $products[] = [
                'name' => (string) $product->getName(),
                'url' => (string) $product->getProductUrl(),
                'image' => $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl(),
                'price' => $price > 0 ? $this->priceCurrency->format($price, false) : '',
            ];
        }

        return $products;
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    private function getCategories(string $term): array
    {
        $store = $this->storeManager->getStore();
        $rootId = (int) $this->storeManager->getGroup()->getRootCategoryId();
        $collection = $this->categoryCollectionFactory->create()
            ->setStoreId((int) $store->getId())
            ->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addAttributeToFilter('name', ['like' => '%' . $this->escapeLike($term) . '%'])
            ->addIsActiveFilter()
            ->addPathFilter('^1/' . $rootId . '/')
            ->addUrlRewriteToResult()
            ->setPageSize(4);

        $categories = [];
        /** @var Category $category */
        foreach ($collection as $category) {
            $categories[] = ['name' => (string) $category->getName(), 'url' => (string) $category->getUrl()];
        }

        return $categories;
    }

    /**
     * @return string[]
     */
    private function getTerms(string $term): array
    {
        $collection = $this->queryCollectionFactory->create()
            ->setQueryFilter($term)
            ->addStoreFilter([(int) $this->storeManager->getStore()->getId()])
            ->addFieldToFilter('num_results', ['gt' => 0])
            ->setOrder('popularity', 'DESC')
            ->setPageSize(4);

        $terms = [];
        /** @var Query $query */
        foreach ($collection as $query) {
            $text = (string) $query->getQueryText();
            if (mb_strtolower($text) !== mb_strtolower($term)) {
                $terms[] = $text;
            }
        }

        return $terms;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}

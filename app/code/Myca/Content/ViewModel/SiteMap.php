<?php

declare(strict_types=1);

namespace Myca\Content\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Données du plan du site : catégories actives de la boutique, pages CMS publiées et services.
 * Une page désactivée ou supprimée disparaît d'elle-même du plan.
 */
class SiteMap implements ArgumentInterface
{
    /** Pages techniques, accessibles mais sans intérêt dans un plan. */
    private const HIDDEN_PAGES = ['home', 'no-route', 'enable-cookies'];

    /** Pages juridiques, regroupées à part. */
    private const LEGAL_PAGES = ['cgv', 'cgu', 'conditions-vente-professionnelles', 'confidentialite', 'cookies', 'mentions-legales'];

    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array<int, array{name: string, url: string, level: int}> dans l'ordre de l'arborescence
     */
    public function getCategories(): array
    {
        $store = $this->storeManager->getStore();
        $rootId = (int) $this->storeManager->getGroup()->getRootCategoryId();
        $collection = $this->categoryCollectionFactory->create()
            ->setStoreId((int) $store->getId())
            ->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addIsActiveFilter()
            ->addPathFilter('^1/' . $rootId . '/')
            ->addUrlRewriteToResult()
            ->setOrder('path', 'ASC');
        $categories = [];
        /** @var Category $category */
        foreach ($collection as $category) {
            $categories[] = [
                'name' => (string) $category->getName(),
                'url' => (string) $category->getUrl(),
                'level' => max(0, (int) $category->getLevel() - 2),
            ];
        }

        return $categories;
    }

    /**
     * @return array{service: array<int, array{title: string, url: string}>, legal: array<int, array{title: string, url: string}>}
     */
    public function getPages(): array
    {
        $collection = $this->pageCollectionFactory->create()
            ->addStoreFilter((int) $this->storeManager->getStore()->getId())
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('identifier', ['nin' => self::HIDDEN_PAGES])
            ->setOrder('title', 'ASC');
        $pages = ['service' => [], 'legal' => []];
        foreach ($collection as $page) {
            $identifier = (string) $page->getIdentifier();
            $pages[in_array($identifier, self::LEGAL_PAGES, true) ? 'legal' : 'service'][] = [
                'title' => (string) $page->getTitle(),
                'url' => $this->urlBuilder->getUrl(null, ['_direct' => $identifier]),
            ];
        }

        return $pages;
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    public function getServices(): array
    {
        $links = [
            [__('Mon compte'), 'customer/account'],
            [__('Créer un compte'), 'customer/account/create'],
            [__('Suivi de commande'), 'sales/guest/form'],
            [__('Contact / Service client'), 'contact'],
            [__('Devenir partenaire'), 'devenir-partenaire'],
        ];

        return array_map(fn (array $link) => [
            'title' => (string) $link[0],
            'url' => $this->urlBuilder->getUrl(null, ['_direct' => $link[1]]),
        ], $links);
    }
}

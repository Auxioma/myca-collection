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

    /** Pages de la marque, dans l'ordre d'affichage. */
    private const BRAND_PAGES = ['notre-histoire', 'nos-engagements', 'qualite-matieres', 'passeport-du-vetement'];

    /** Catégorie racine des collections et lignes rangées avec elles. */
    public const COLLECTIONS_KEY = 'collections';
    private const COLLECTION_LINES = ['myca-signature'];

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
     * @return array<int, array{name: string, url: string, level: int, key: string}> dans l'ordre de l'arborescence
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
            ->setOrder('position', 'ASC');
        $children = [];
        /** @var Category $category */
        foreach ($collection as $category) {
            $children[(int) $category->getParentId()][] = $category;
        }
        $categories = [];
        // Parcours en profondeur : chaque catégorie suivie de ses enfants, dans l'ordre défini dans l'admin.
        $walk = function (int $parentId) use (&$walk, &$categories, $children): void {
            foreach ($children[$parentId] ?? [] as $category) {
                $categories[] = [
                    'name' => (string) $category->getName(),
                    'url' => (string) $category->getUrl(),
                    'level' => max(0, (int) $category->getLevel() - 2),
                    'key' => (string) $category->getUrlKey(),
                ];
                $walk((int) $category->getId());
            }
        };
        $walk($rootId);

        return $categories;
    }

    /**
     * Catégories réparties entre « Collections » (la catégorie collections, ses sous-catégories et la ligne Signature)
     * et « Produits » (les autres catégories de premier niveau et leurs enfants).
     *
     * @return array{collections: array<int, array{title: string, url: string, level: int}>, products: array<int, array{title: string, url: string, level: int}>}
     */
    public function getCategoryGroups(): array
    {
        $groups = ['collections' => [], 'lines' => [], 'products' => []];
        $group = 'products';
        foreach ($this->getCategories() as $category) {
            if ($category['level'] === 0) {
                $group = match (true) {
                    $category['key'] === self::COLLECTIONS_KEY => 'collections',
                    in_array($category['key'], self::COLLECTION_LINES, true) => 'lines',
                    default => 'products',
                };
            }
            $isRoot = $category['key'] === self::COLLECTIONS_KEY;
            $groups[$group][] = [
                'title' => $isRoot ? (string) __('Toutes les collections') : $category['name'],
                'url' => $category['url'],
                'level' => $group === 'products' ? $category['level'] : 0,
            ];
        }

        // Les lignes (MYCA Signature) se rangent après les collections.
        return ['collections' => [...$groups['collections'], ...$groups['lines']], 'products' => $groups['products']];
    }

    /**
     * Image de la bannière (photo de l'accueil, modifiable dans la médiathèque).
     */
    public function getHeroImageUrl(): string
    {
        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]) . 'wysiwyg/myca/accueil-banniere.webp';
    }

    /**
     * @return array{brand: array<int, array{title: string, url: string}>, service: array<int, array{title: string, url: string}>, legal: array<int, array{title: string, url: string}>}
     */
    public function getPages(): array
    {
        $collection = $this->pageCollectionFactory->create()
            ->addStoreFilter((int) $this->storeManager->getStore()->getId())
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('identifier', ['nin' => self::HIDDEN_PAGES])
            ->setOrder('title', 'ASC');
        $pages = ['brand' => [], 'service' => [], 'legal' => []];
        $brandOrder = array_flip(self::BRAND_PAGES);
        foreach ($collection as $page) {
            $identifier = (string) $page->getIdentifier();
            $group = match (true) {
                isset($brandOrder[$identifier]) => 'brand',
                in_array($identifier, self::LEGAL_PAGES, true) => 'legal',
                default => 'service',
            };
            $link = [
                'title' => (string) $page->getTitle(),
                'url' => $this->urlBuilder->getUrl(null, ['_direct' => $identifier]),
            ];
            if ($group === 'brand') {
                $pages['brand'][$brandOrder[$identifier]] = $link;
            } else {
                $pages[$group][] = $link;
            }
        }
        ksort($pages['brand']);
        $pages['brand'] = array_values($pages['brand']);

        return $pages;
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    public function getServices(): array
    {
        return $this->links([
            [__('Suivi de commande'), 'sales/guest/form'],
            [__('Paiements sécurisés'), 'faq#paiement'],
        ]);
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    public function getAccountLinks(): array
    {
        return $this->links([
            [__('Créer mon compte'), 'customer/account/create'],
            [__('Se connecter'), 'customer/account/login'],
            [__('Mon compte'), 'customer/account'],
            [__('Mes commandes'), 'sales/order/history'],
            [__('Mes favoris'), 'wishlist'],
        ]);
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    public function getContactLinks(): array
    {
        return $this->links([
            [__('Nous contacter'), 'contact'],
            [__('Devenir partenaire'), 'devenir-partenaire'],
        ]);
    }

    /**
     * @param array<int, array{0: \Magento\Framework\Phrase|string, 1: string}> $links
     * @return array<int, array{title: string, url: string}>
     */
    private function links(array $links): array
    {
        return array_map(fn (array $link) => [
            'title' => (string) $link[0],
            'url' => $this->urlBuilder->getUrl(null, ['_direct' => $link[1]]),
        ], $links);
    }
}

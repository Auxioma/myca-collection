<?php

declare(strict_types=1);

namespace Myca\Dropship\ViewModel;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Myca\Dropship\Model\OrderConfig;
use Myca\Dropship\Model\PartnerOrderService;
use Myca\Dropship\Model\PartnerPricing;
use Myca\Partner\Model\CurrentPartner;

/**
 * Données de l'écran M17 : modèles commandables (couleurs, tailles, stock vendable par variante, prix public et
 * tarif partenaire indicatif), état de la commande en cours et rappels des conditions.
 */
class OrderForm implements ArgumentInterface
{
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Configurable $configurableType,
        private readonly SwatchHelper $swatchHelper,
        private readonly ImageHelper $imageHelper,
        private readonly CatalogHelper $catalogHelper,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly CountryInformationAcquirerInterface $countryInformation,
        private readonly UrlInterface $urlBuilder,
        private readonly CurrentPartner $currentPartner,
        private readonly PartnerOrderService $service,
        private readonly OrderConfig $config
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->service->getState();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getModels(): array
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'small_image', 'myca_collection', 'myca_tagline', 'myca_materials'])
            ->addAttributeToFilter('type_id', ['eq' => Configurable::TYPE_CODE])
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->addAttributeToFilter('visibility', ['in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH]])
            ->addWebsiteFilter((int) $this->storeManager->getWebsite()->getId())
            ->addCategoryIds()
            ->setOrder('name', 'ASC');
        $categories = $this->getProductTypeCategories();
        $models = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $model = $this->describe($product, $categories);
            if ($model !== null) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * @return array{dropship: float, stock_tiers: array<int, array{min_qty: int, discount: float}>, stock_minimum: int,
     *     dropship_shipping: float, stock_shipping: float, stock_free_from: float, contract: array<string, float|null>}
     */
    public function getConditions(): array
    {
        $partner = $this->currentPartner->getActive();
        $contract = ['dropship' => null, 'stock' => null];
        if ($partner instanceof AbstractModel) {
            foreach (['dropship', 'stock'] as $mode) {
                $value = $partner->getData($mode . '_discount');
                $contract[$mode] = $value !== null && $value !== '' ? (float) $value : null;
            }
        }

        return [
            'dropship' => $this->config->getDropshipDiscount(),
            'stock_tiers' => $this->config->getStockTiers(),
            'stock_minimum' => $this->config->getStockMinimumPerColor(),
            'dropship_shipping' => $this->config->getShippingPrice(PartnerPricing::MODE_DROPSHIP),
            'stock_shipping' => $this->config->getShippingPrice(PartnerPricing::MODE_STOCK),
            'stock_free_from' => $this->config->getStockFreeShippingFrom(),
            'contract' => $contract,
        ];
    }

    /**
     * @return array<string, string> code pays => nom, pays desservis pour la livraison professionnelle
     */
    public function getServedCountries(): array
    {
        $countries = [];
        foreach ($this->service->getServedCountries() as $code) {
            try {
                $countries[$code] = (string) $this->countryInformation->getCountryInfo($code)->getFullNameLocale();
            } catch (\Exception) {
                $countries[$code] = $code;
            }
        }
        asort($countries);

        return $countries;
    }

    /**
     * Adresse de livraison de l'achat de stock (profil boutique).
     */
    public function getBoutiqueAddress(): string
    {
        $partner = $this->currentPartner->getActive();
        if (!$partner instanceof AbstractModel) {
            return '';
        }
        $parts = array_filter([
            $partner->getData('company_name'),
            $partner->getData('street'),
            trim($partner->getData('postcode') . ' ' . $partner->getData('city')),
            $partner->getData('country_id'),
        ]);

        return implode(', ', array_map('strval', $parts));
    }

    /**
     * @return array<string, string>
     */
    public function getUrls(): array
    {
        $urls = [];
        foreach (['mode', 'ajouter', 'retirer', 'livraison', 'valider'] as $action) {
            $urls[$action] = $this->urlBuilder->getUrl('partenaire/commander/' . $action);
        }
        $urls['terms'] = $this->urlBuilder->getUrl(null, ['_direct' => 'conditions-vente-professionnelles']);
        $urls['profile'] = $this->urlBuilder->getUrl('partenaire/profil');

        return $urls;
    }

    /**
     * @param array<int, string> $categories
     * @return array<string, mixed>|null
     */
    private function describe(Product $product, array $categories): ?array
    {
        $attributes = [];
        foreach ($this->configurableType->getConfigurableAttributesAsArray($product) as $attribute) {
            $values = [];
            foreach ($attribute['values'] as $value) {
                $values[(int) $value['value_index']] = (string) ($value['store_label'] ?? $value['label']);
            }
            $attributes[(string) $attribute['attribute_code']] = $values;
        }
        $colors = $attributes[PartnerOrderService::COLOR_ATTRIBUTE] ?? [];
        $sizes = $attributes[PartnerOrderService::SIZE_ATTRIBUTE] ?? [];
        if ($colors === [] && $sizes === []) {
            return null;
        }
        // Modèle sans couleur ou sans taille (casquette « taille unique ») : une valeur fictive 0.
        $colors = $colors ?: [0 => (string) __('Unique')];
        $sizes = $sizes ?: [0 => (string) __('Taille unique')];
        $swatches = $this->swatchHelper->getSwatchesByOptionsId(array_keys($colors));
        $stock = [];
        $publicPrice = null;
        $cheapest = $product;
        /** @var Product $child */
        foreach ($this->configurableType->getUsedProducts($product) as $child) {
            $color = isset($attributes[PartnerOrderService::COLOR_ATTRIBUTE]) ? (int) $child->getData(PartnerOrderService::COLOR_ATTRIBUTE) : 0;
            $size = isset($attributes[PartnerOrderService::SIZE_ATTRIBUTE]) ? (int) $child->getData(PartnerOrderService::SIZE_ATTRIBUTE) : 0;
            $stock[$color][$size] = (int) $child->getStatus() === Status::STATUS_ENABLED ? $this->getSalableQty((string) $child->getSku()) : 0;
            $price = (float) $child->getPrice();
            if ($publicPrice === null || $price < $publicPrice) {
                $publicPrice = $price;
                $cheapest = $child;
            }
        }
        $publicPrice ??= (float) $product->getPrice();
        $category = '';
        foreach ((array) $product->getCategoryIds() as $categoryId) {
            if (isset($categories[(int) $categoryId])) {
                $category = $categories[(int) $categoryId];
                break;
            }
        }
        $collection = $product->getData('myca_collection') ? $product->getAttributeText('myca_collection') : '';

        return [
            'id' => (int) $product->getId(),
            'name' => (string) $product->getName(),
            'sku' => (string) $product->getSku(),
            'category' => $category,
            'collection' => is_string($collection) ? $collection : '',
            'tagline' => $this->tagline($product),
            'image' => (string) $this->imageHelper->init($product, 'product_small_image')->getUrl(),
            'colors' => array_map(
                static fn (int $id, string $label) => ['id' => $id, 'label' => $label, 'hex' => (string) ($swatches[$id]['value'] ?? '')],
                array_keys($colors),
                array_values($colors)
            ),
            'sizes' => array_map(static fn (int $id, string $label) => ['id' => $id, 'label' => $label], array_keys($sizes), array_values($sizes)),
            'stock' => $stock,
            'available' => array_sum(array_map('array_sum', $stock)),
            'public_price' => round($publicPrice, 2),
            'public_price_excl_tax' => round((float) $this->catalogHelper->getTaxPrice($cheapest, $publicPrice, false, null, null, null, null, true), 2),
        ];
    }

    /**
     * Matière ou accroche en texte simple ; une donnée encore « À FOURNIR » n'est pas affichée.
     */
    private function tagline(Product $product): string
    {
        foreach (['myca_materials', 'myca_tagline'] as $attribute) {
            $text = trim(html_entity_decode(strip_tags((string) $product->getData($attribute))));
            if ($text !== '' && stripos($text, 'À FOURNIR') === false) {
                return $text;
            }
        }

        return '';
    }

    /**
     * Catégories « type de produit » (Polos, Tee-shirts…), hors arborescence des collections.
     *
     * @return array<int, string>
     */
    private function getProductTypeCategories(): array
    {
        $rootId = (int) $this->storeManager->getGroup()->getRootCategoryId();
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key']);
        $collection->addIsActiveFilter();
        $collection->addPathFilter('^1/' . $rootId . '/');
        $collection->addFieldToFilter('level', 2);
        $categories = [];
        foreach ($collection as $category) {
            if ((string) $category->getData('url_key') !== 'collections') {
                $categories[(int) $category->getId()] = (string) $category->getName();
            }
        }

        return $categories;
    }

    private function getSalableQty(string $sku): int
    {
        try {
            $websiteCode = (string) $this->storeManager->getWebsite()->getCode();
            $stockId = (int) $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();

            return max(0, (int) $this->getProductSalableQty->execute($sku, $stockId));
        } catch (LocalizedException) {
            return 0;
        }
    }
}

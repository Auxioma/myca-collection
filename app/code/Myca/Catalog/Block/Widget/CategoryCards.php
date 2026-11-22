<?php

declare(strict_types=1);

namespace Myca\Catalog\Block\Widget;

use Magento\Catalog\Model\Category;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Myca\Catalog\ViewModel\CategoryCards as CategoryCardsViewModel;

/**
 * Widget « MYCA – Cartes de catégories », à insérer dans une page Page Builder (élément HTML ou Texte).
 * Deux présentations : « category » (accès par type de produit, P01) et « collection » (capsules, P01/P02).
 */
class CategoryCards extends Template implements BlockInterface
{
    public const STYLE_CATEGORY = 'category';
    public const STYLE_COLLECTION = 'collection';

    /** @var string */
    protected $_template = 'Myca_Catalog::widget/category-cards.phtml';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly CategoryCardsViewModel $categoryCards,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Category[]
     */
    public function getCategories(): array
    {
        $urlKeys = array_filter(array_map('trim', explode(',', (string) $this->getData('url_keys'))));

        return $urlKeys ? $this->categoryCards->getByUrlKeys($urlKeys) : [];
    }

    public function getStyle(): string
    {
        return $this->getData('card_style') === self::STYLE_COLLECTION ? self::STYLE_COLLECTION : self::STYLE_CATEGORY;
    }

    public function getProductCount(Category $category): int
    {
        return $this->categoryCards->getProductCount($category);
    }

    public function renderCollectionCard(Category $category): string
    {
        return $this->getLayout()
            ->createBlock(Template::class)
            ->setTemplate('Magento_Catalog::category/collection-card.phtml')
            ->setData('category', $category)
            ->toHtml();
    }

    /**
     * @return string[]
     */
    public function getCacheKeyInfo(): array
    {
        return array_merge(parent::getCacheKeyInfo(), [
            (string) $this->getData('url_keys'),
            $this->getStyle(),
            (string) $this->getData('title'),
        ]);
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return array_merge(
            [Category::CACHE_TAG],
            ...array_map(static fn (Category $category) => $category->getIdentities(), $this->getCategories())
        );
    }
}

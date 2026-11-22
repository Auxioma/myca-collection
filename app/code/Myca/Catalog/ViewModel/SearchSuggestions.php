<?php

declare(strict_types=1);

namespace Myca\Catalog\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Model\Query;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * « Recherches populaires » de la page de recherche (P05), d'après les statistiques de recherche Magento.
 */
class SearchSuggestions implements ArgumentInterface
{
    public function __construct(
        private readonly CollectionFactory $queryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string[]
     */
    public function getPopularTerms(int $limit = 4): array
    {
        $collection = $this->queryCollectionFactory->create()
            ->setPopularQueryFilter((int) $this->storeManager->getStore()->getId())
            ->addFieldToFilter('num_results', ['gt' => 0])
            ->setPageSize($limit);

        $terms = [];
        /** @var Query $query */
        foreach ($collection as $query) {
            $terms[] = (string) $query->getQueryText();
        }

        return $terms;
    }
}

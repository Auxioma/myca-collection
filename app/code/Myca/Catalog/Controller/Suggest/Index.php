<?php

declare(strict_types=1);

namespace Myca\Catalog\Controller\Suggest;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Search\Helper\Data as SearchHelper;
use Myca\Catalog\Model\SearchPreview;

/**
 * Autocomplétion du champ de recherche : renvoie l'aperçu (produits, catégories, recherches) en JSON.
 * Saisie trop courte ou trop longue : réponse vide.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly SearchPreview $searchPreview,
        private readonly SearchHelper $searchHelper
    ) {
    }

    public function execute(): Json
    {
        $term = trim((string) $this->request->getParam('q'));
        $length = mb_strlen($term);
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'private, max-age=60', true);
        if ($length < max(2, (int) $this->searchHelper->getMinQueryLength())
            || $length > (int) $this->searchHelper->getMaxQueryLength()) {
            return $result->setData(['products' => [], 'categories' => [], 'terms' => []]);
        }

        return $result->setData($this->searchPreview->get($term));
    }
}

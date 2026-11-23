<?php

declare(strict_types=1);

namespace Myca\Rma\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Lien « Traiter » vers la fiche d'une demande.
 */
class Actions extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $dataSource['data']['items'][$index][$this->getData('name')] = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl('myca_rma/rma/view', ['id' => $item['rma_id']]),
                    'label' => __('Traiter'),
                ],
            ];
        }

        return $dataSource;
    }
}

<?php

declare(strict_types=1);

namespace Myca\Partner\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Lien « Ouvrir » d'une ligne de grille ; la route est donnée par l'argument « editUrl » du composant.
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
        $field = (string) $this->getData('config/indexField');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $dataSource['data']['items'][$index][$this->getData('name')] = [
                'edit' => ['href' => $this->urlBuilder->getUrl((string) $this->getData('editUrl'), ['id' => $item[$field]]), 'label' => __('Ouvrir')],
            ];
        }

        return $dataSource;
    }
}

<?php

declare(strict_types=1);

namespace Myca\Rma\Ui\Component\Listing\Column;

use Magento\Sales\Ui\Component\Listing\Column\Price;

/**
 * Montant remboursé : vide tant qu'aucun avoir n'est émis (et non « 0,00 € »).
 */
class RefundedAmount extends Price
{
    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        $name = (string) $this->getData('name');
        $empty = [];
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            if (($item[$name] ?? null) === null) {
                $empty[] = $index;
            }
        }
        $dataSource = parent::prepareDataSource($dataSource);
        foreach ($empty as $index) {
            $dataSource['data']['items'][$index][$name] = '';
        }

        return $dataSource;
    }
}

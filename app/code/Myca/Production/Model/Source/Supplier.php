<?php

declare(strict_types=1);

namespace Myca\Production\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Myca\Production\Model\ProductionManagement;

class Supplier implements OptionSourceInterface
{
    public function __construct(private readonly ProductionManagement $productionManagement)
    {
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->productionManagement->getSupplierOptions(false) as $id => $name) {
            $options[] = ['value' => $id, 'label' => $name];
        }

        return $options;
    }
}

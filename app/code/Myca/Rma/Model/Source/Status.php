<?php

declare(strict_types=1);

namespace Myca\Rma\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Myca\Rma\Model\StatusFlow;

class Status implements OptionSourceInterface
{
    public function __construct(private readonly StatusFlow $statusFlow)
    {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->statusFlow->getLabels() as $value => $status) {
            $options[] = ['value' => $value, 'label' => $status['label']];
        }

        return $options;
    }
}

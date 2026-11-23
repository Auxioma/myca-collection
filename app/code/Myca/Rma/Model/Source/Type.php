<?php

declare(strict_types=1);

namespace Myca\Rma\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Myca\Rma\Model\Reasons;

class Type implements OptionSourceInterface
{
    public function __construct(private readonly Reasons $reasons)
    {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->reasons->getTypeLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}

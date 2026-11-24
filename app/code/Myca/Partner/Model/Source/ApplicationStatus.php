<?php

declare(strict_types=1);

namespace Myca\Partner\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApplicationStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'pending', 'label' => (string) __('À examiner')],
            ['value' => 'approved', 'label' => (string) __('Acceptée')],
            ['value' => 'rejected', 'label' => (string) __('Refusée')],
        ];
    }
}

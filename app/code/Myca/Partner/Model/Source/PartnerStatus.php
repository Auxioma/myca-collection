<?php

declare(strict_types=1);

namespace Myca\Partner\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PartnerStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'active', 'label' => (string) __('Actif')],
            ['value' => 'pending', 'label' => (string) __('En attente')],
            ['value' => 'suspended', 'label' => (string) __('Suspendu')],
            ['value' => 'rejected', 'label' => (string) __('Refusé')],
        ];
    }
}

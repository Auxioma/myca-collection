<?php

declare(strict_types=1);

namespace Myca\Production\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::getLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function getLabels(): array
    {
        return [
            'planned' => (string) __('Prévisionnel'),
            'committed' => (string) __('Engagé (bon de commande)'),
            'in_production' => (string) __('En fabrication'),
            'receiving' => (string) __('Réception en cours'),
            'received' => (string) __('Reçu (clos)'),
            'canceled' => (string) __('Annulé'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Myca\Loyalty\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Myca\Loyalty\Model\Ledger;

class Event implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Ledger::EARN, 'label' => (string) __('Gain (en attente)')],
            ['value' => Ledger::VALIDATE, 'label' => (string) __('Validation')],
            ['value' => Ledger::CANCEL, 'label' => (string) __('Annulation de commande')],
            ['value' => Ledger::REFUND, 'label' => (string) __('Retour / remboursement')],
            ['value' => Ledger::REDEEM, 'label' => (string) __('Bon d’achat')],
            ['value' => Ledger::RESTORE, 'label' => (string) __('Restitution')],
            ['value' => Ledger::EXPIRE, 'label' => (string) __('Expiration')],
            ['value' => Ledger::ADJUST, 'label' => (string) __('Ajustement manuel')],
        ];
    }
}

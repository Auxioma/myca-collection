<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

/**
 * Notifications proposées à un utilisateur partenaire (M26), activées par défaut.
 */
class NotificationPreferences
{
    /**
     * @return array<string, string> code => libellé
     */
    public function getOptions(): array
    {
        return [
            'new_orders' => (string) __('Nouvelles commandes'),
            'shipping' => (string) __('Suivi des livraisons'),
            'returns' => (string) __('Retours & SAV'),
            'invoices' => (string) __('Factures et règlements'),
            'promotions' => (string) __('Actualités et promotions MYCA (opt-in)'),
            'weekly_summary' => (string) __('Résumé hebdomadaire (ventes & marges)'),
        ];
    }

    /**
     * @param array<string, bool> $stored
     * @return array<string, bool>
     */
    public function resolve(array $stored): array
    {
        $values = [];
        foreach (array_keys($this->getOptions()) as $code) {
            $values[$code] = $stored[$code] ?? true;
        }

        return $values;
    }
}

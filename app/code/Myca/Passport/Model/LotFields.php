<?php

declare(strict_types=1);

namespace Myca\Passport\Model;

/**
 * Données d'un lot et leur statut : certifiée (vérifiée par MYCA), déclarée (déclaration du fabricant) ou en cours
 * de validation — cette dernière n'est jamais affichée comme un fait.
 */
class LotFields
{
    public const CERTIFIED = 'certified';
    public const DECLARED = 'declared';
    public const PENDING = 'pending';

    /**
     * Champs indispensables au certificat d'authenticité : ils doivent être certifiés et renseignés.
     */
    private const CERTIFICATE_FIELDS = ['model_reference', 'collection'];

    /**
     * @return array<string, string> champ → libellé, dans l'ordre de la maquette
     */
    public function getFields(): array
    {
        return [
            'collection' => (string) __('Collection'),
            'model_reference' => (string) __('Référence modèle'),
            'cut' => (string) __('Coupe'),
            'material' => (string) __('Matière'),
            'grammage' => (string) __('Grammage'),
            'manufactured_on' => (string) __('Date de fabrication'),
            'origin' => (string) __('Origine'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getStatusLabels(): array
    {
        return [
            self::CERTIFIED => (string) __('Certifiée par MYCA'),
            self::DECLARED => (string) __('Déclarée par le fabricant'),
            self::PENDING => (string) __('En cours de validation'),
        ];
    }

    /**
     * @param array<string, string> $statuses
     */
    public function getStatus(array $statuses, string $field): string
    {
        $status = $statuses[$field] ?? self::PENDING;

        return array_key_exists($status, $this->getStatusLabels()) ? $status : self::PENDING;
    }

    /**
     * Valeur publiable : rien tant que la donnée est en cours de validation ou vide.
     *
     * @param array<string, string> $statuses
     */
    public function getPublicValue(array $statuses, string $field, ?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $this->getStatus($statuses, $field) === self::PENDING ? null : $value;
    }

    /**
     * @param array<string, mixed> $lot
     * @param array<string, string> $statuses
     */
    public function canCertify(array $lot, array $statuses): bool
    {
        foreach (self::CERTIFICATE_FIELDS as $field) {
            if ($this->getStatus($statuses, $field) !== self::CERTIFIED || trim((string) ($lot[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }
}

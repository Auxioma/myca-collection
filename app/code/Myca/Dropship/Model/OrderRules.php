<?php

declare(strict_types=1);

namespace Myca\Dropship\Model;

/**
 * Contrôles d'une commande partenaire, sans accès à la base : minimum par modèle et couleur (achat de stock) et
 * destinataire final (dropshipping), limité aux données nécessaires à la livraison.
 */
class OrderRules
{
    public const RECIPIENT_FIELDS = ['firstname', 'lastname', 'street', 'postcode', 'city', 'country_id', 'telephone'];

    /**
     * @param array<string, array{label: string, qty: int}> $quantities quantité par « modèle|couleur »
     * @return string[] modèles et couleurs sous le minimum
     */
    public function getBelowMinimum(array $quantities, int $minimum): array
    {
        if ($minimum <= 0) {
            return [];
        }
        $below = [];
        foreach ($quantities as $line) {
            if ($line['qty'] < $minimum) {
                $below[] = $line['label'];
            }
        }

        return $below;
    }

    /**
     * @param array<string, mixed> $input
     * @param string[] $servedCountries
     * @return array{recipient: array<string, string>, errors: string[]}
     */
    public function normalizeRecipient(array $input, array $servedCountries): array
    {
        $recipient = [];
        foreach (self::RECIPIENT_FIELDS as $field) {
            $recipient[$field] = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($input[$field] ?? '')) ?? ''), 0, 255);
        }
        $recipient['country_id'] = strtoupper($recipient['country_id']);
        $errors = [];
        foreach (['firstname' => 'le prénom', 'lastname' => 'le nom', 'street' => 'l’adresse', 'postcode' => 'le code postal', 'city' => 'la ville'] as $field => $label) {
            if ($recipient[$field] === '') {
                $errors[] = 'Indiquez ' . $label . ' du destinataire.';
            }
        }
        if (!in_array($recipient['country_id'], $servedCountries, true)) {
            $errors[] = 'Ce pays n’est pas desservi.';
        }
        if ($recipient['country_id'] === 'FR' && $recipient['postcode'] !== '' && !preg_match('/^\d{5}$/', $recipient['postcode'])) {
            $errors[] = 'Le code postal doit comporter 5 chiffres.';
        }
        $digits = (string) preg_replace('/\D/', '', $recipient['telephone']);
        if (strlen($digits) < 9 || strlen($digits) > 15) {
            $errors[] = 'Indiquez un téléphone valide : le transporteur prévient le destinataire.';
        }

        return ['recipient' => $recipient, 'errors' => $errors];
    }
}

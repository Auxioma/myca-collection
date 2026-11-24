<?php

declare(strict_types=1);

namespace Myca\Partner\Model;

use Myca\Partner\Api\Data\PartnerInterface;

/**
 * Contrôles des données d'une boutique (candidature P17, création dans l'administration), sans accès à la base.
 */
class PartnerValidator
{
    public const VOLUMES = ['lt5k' => 'Moins de 5 000 €', '5k-20k' => '5 000 – 20 000 €', '20k-50k' => '20 000 – 50 000 €', 'gt50k' => 'Plus de 50 000 €'];

    /**
     * SIRET : 14 chiffres, clé de Luhn (exception de La Poste, SIREN 356 000 000 : somme des chiffres multiple de 5).
     */
    public function normalizeSiret(string $siret): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $siret);
        if (strlen($digits) !== 14) {
            return null;
        }
        if (str_starts_with($digits, '356000000')) {
            return array_sum(str_split($digits)) % 5 === 0 ? $digits : null;
        }
        $sum = 0;
        foreach (str_split(strrev($digits)) as $index => $digit) {
            $value = (int) $digit * ($index % 2 === 1 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return $sum % 10 === 0 ? $digits : null;
    }

    /**
     * N° de TVA intracommunautaire : la clé est vérifiée pour la France ; format européen général sinon.
     * Pour un numéro français, le SIREN doit être celui du SIRET.
     */
    public function normalizeVat(string $vat, ?string $siret = null): ?string
    {
        $value = strtoupper((string) preg_replace('/[\s.-]/', '', $vat));
        if (preg_match('/^FR(\d{2})(\d{9})$/', $value, $match)) {
            $key = (12 + 3 * ((int) $match[2] % 97)) % 97;
            if ((int) $match[1] !== $key || ($siret !== null && !str_starts_with($siret, $match[2]))) {
                return null;
            }

            return $value;
        }

        return preg_match('/^(AT|BE|BG|CY|CZ|DE|DK|EE|EL|ES|FI|HR|HU|IE|IT|LT|LU|LV|MT|NL|PL|PT|RO|SE|SI|SK|XI)[A-Z0-9]{2,12}$/', $value)
            ? $value
            : null;
    }

    public function normalizePhone(string $phone): ?string
    {
        $value = trim($phone);
        $digits = (string) preg_replace('/\D/', '', $value);

        return preg_match('/^\+?[\d\s.()-]+$/', $value) && strlen($digits) >= 9 && strlen($digits) <= 15 ? $value : null;
    }

    /**
     * Mode souhaité : « dropship », « stock » ou « both ».
     *
     * @return string[]|null
     */
    public function parseSalesModes(string $choice): ?array
    {
        return match ($choice) {
            PartnerInterface::MODE_DROPSHIP => [PartnerInterface::MODE_DROPSHIP],
            PartnerInterface::MODE_STOCK => [PartnerInterface::MODE_STOCK],
            'both' => [PartnerInterface::MODE_DROPSHIP, PartnerInterface::MODE_STOCK],
            default => null,
        };
    }

    /**
     * Prénom et nom à partir du « Nom du contact » (le dernier mot est le nom).
     *
     * @return array{0: string, 1: string}
     */
    public function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        if (count($parts) < 2) {
            return [trim($fullName), trim($fullName)];
        }
        $lastname = (string) array_pop($parts);

        return [implode(' ', $parts), $lastname];
    }
}

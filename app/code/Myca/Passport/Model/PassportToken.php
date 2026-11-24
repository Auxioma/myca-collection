<?php

declare(strict_types=1);

namespace Myca\Passport\Model;

/**
 * Identifiant d'un passeport : 20 caractères aléatoires en base 32 de Crockford (100 bits, non prédictible),
 * lisible sur une étiquette (pas de I, L, O ni U). Il sert d'URL au QR code et de numéro d'authenticité.
 */
class PassportToken
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const LENGTH = 20;

    public function generate(): string
    {
        $bytes = random_bytes(self::LENGTH);
        $token = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $token .= self::ALPHABET[ord($bytes[$i]) % 32];
        }

        return $token;
    }

    /**
     * Saisie d'un client ou du support : majuscules, sans préfixe, tirets ni espaces ; I et L lus 1, O lu 0.
     */
    public function normalize(string $input): string
    {
        $value = strtoupper(trim($input));
        $value = (string) preg_replace('#^.*/PASSEPORT/#', '', $value);
        $value = (string) preg_replace('/^MYCA-?/', '', $value);
        $value = str_replace(['-', ' ', 'I', 'L', 'O'], ['', '', '1', '1', '0'], $value);

        return $value;
    }

    public function isValid(string $token): bool
    {
        return (bool) preg_match('/^[' . self::ALPHABET . ']{' . self::LENGTH . '}$/', $token);
    }

    /**
     * Numéro d'authenticité affiché : MYCA-XXXX-XXXX-XXXX-XXXX-XXXX.
     */
    public function format(string $token): string
    {
        return 'MYCA-' . implode('-', str_split($token, 4));
    }
}

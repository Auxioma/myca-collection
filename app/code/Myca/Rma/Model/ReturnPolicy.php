<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

/**
 * Règles du cahier des charges (retours B2C et B2B), sans accès à la base :
 * - achat MYCA en ligne : MYCA rembourse le payeur d'origine ;
 * - dropshipping : la boutique rembourse son client, MYCA émet un avoir à la boutique ;
 * - stock de la boutique physique : retour au comptoir, aucun remboursement MYCA ; seul un dossier de garantie
 *   fournisseur peut être déposé par la boutique.
 */
class ReturnPolicy
{
    public const ORIGIN_ONLINE = 'online';
    public const ORIGIN_DROPSHIP = 'dropship';
    public const ORIGIN_STORE = 'store';

    public const TYPE_RETURN = 'return';
    public const TYPE_EXCHANGE = 'exchange';
    public const TYPE_WARRANTY = 'warranty';

    public const PARTY_MYCA = 'myca';
    public const PARTY_PARTNER = 'partner';
    public const PARTY_NONE = 'none';

    public const ORIGINS = [self::ORIGIN_ONLINE, self::ORIGIN_DROPSHIP, self::ORIGIN_STORE];

    /**
     * Qui rembourse le consommateur final.
     */
    public function getRefundParty(string $origin): string
    {
        return match ($origin) {
            self::ORIGIN_ONLINE => self::PARTY_MYCA,
            self::ORIGIN_DROPSHIP => self::PARTY_PARTNER,
            self::ORIGIN_STORE => self::PARTY_NONE,
            default => throw new \InvalidArgumentException(sprintf('Origine de retour inconnue : %s', $origin)),
        };
    }

    /**
     * MYCA émet-elle un remboursement (en ligne) ou un avoir (partenaire) sur la commande passée chez MYCA ?
     */
    public function issuesMycaCredit(string $origin, string $type): bool
    {
        if ($type === self::TYPE_EXCHANGE) {
            return false;
        }

        return $origin !== self::ORIGIN_STORE || $type === self::TYPE_WARRANTY;
    }

    /**
     * @return string[]
     */
    public function getAllowedTypes(string $origin): array
    {
        return $origin === self::ORIGIN_STORE
            ? [self::TYPE_WARRANTY]
            : [self::TYPE_RETURN, self::TYPE_EXCHANGE, self::TYPE_WARRANTY];
    }

    /**
     * Délai de la demande : rétractation légale de 14 jours, 30 jours seulement si la politique commerciale est
     * activée ; la garantie (défaut, non-conformité) a son propre délai.
     */
    public function getPolicyDays(string $type, bool $commercial30Days, int $warrantyDays): int
    {
        if ($type === self::TYPE_WARRANTY) {
            return $warrantyDays;
        }

        return $commercial30Days ? 30 : 14;
    }

    /**
     * Le délai court à partir de la livraison : tant qu'elle n'est pas confirmée, il n'a pas commencé.
     */
    public function getDeadline(?\DateTimeImmutable $deliveredAt, int $days): ?\DateTimeImmutable
    {
        return $deliveredAt?->modify(sprintf('+%d days', $days))->setTime(23, 59, 59);
    }

    public function isWithinDeadline(?\DateTimeImmutable $deliveredAt, int $days, \DateTimeImmutable $now): bool
    {
        $deadline = $this->getDeadline($deliveredAt, $days);

        return $deadline === null || $now <= $deadline;
    }

    /**
     * Frais de livraison remboursés : seulement à un consommateur qui se rétracte pour toute sa commande MYCA en
     * ligne. L'avoir d'une boutique suit son contrat (à valider), sans port par défaut.
     *
     * @param array<int, float> $returnedQtys
     * @param array<int, float> $refundableQtys
     */
    public function refundsShipping(string $origin, string $type, array $returnedQtys, array $refundableQtys): bool
    {
        return $origin === self::ORIGIN_ONLINE && $this->isFullWithdrawal($type, $returnedQtys, $refundableQtys);
    }

    /**
     * Le remboursement est-il intégral au sens de la rétractation ? Dans ce cas seulement, les frais de livraison
     * standard initiaux sont remboursés ; un retour partiel ne les rembourse pas et ne les refacture pas non plus.
     *
     * @param array<int, float> $returnedQtys quantités retournées, par ligne de commande
     * @param array<int, float> $refundableQtys quantités encore remboursables, par ligne de commande
     */
    public function isFullWithdrawal(string $type, array $returnedQtys, array $refundableQtys): bool
    {
        if ($type !== self::TYPE_RETURN || !$refundableQtys) {
            return false;
        }
        foreach ($refundableQtys as $itemId => $qty) {
            if ($qty > 0 && ($returnedQtys[$itemId] ?? 0.0) < $qty) {
                return false;
            }
        }

        return true;
    }
}

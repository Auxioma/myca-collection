<?php

declare(strict_types=1);

namespace Myca\Quote\Model;

/**
 * États d'un devis : brouillon › demandé › proposé › accepté › commandé, plus refusé, annulé et expiré.
 * Le client agit sur ses brouillons et répond aux propositions ; MYCA propose ou refuse.
 */
class QuoteStatus
{
    public const DRAFT = 'draft';
    public const REQUESTED = 'requested';
    public const PROPOSED = 'proposed';
    public const ACCEPTED = 'accepted';
    public const ORDERED = 'ordered';
    public const REFUSED = 'refused';
    public const CANCELED = 'canceled';
    public const EXPIRED = 'expired';

    public const ACTOR_CUSTOMER = 'customer';
    public const ACTOR_ADMIN = 'admin';

    private const TRANSITIONS = [
        self::ACTOR_CUSTOMER => [
            self::DRAFT => [self::REQUESTED, self::CANCELED],
            self::REQUESTED => [self::CANCELED],
            self::PROPOSED => [self::ACCEPTED, self::REFUSED],
            self::ACCEPTED => [self::CANCELED],
        ],
        self::ACTOR_ADMIN => [
            self::REQUESTED => [self::PROPOSED, self::REFUSED],
            self::PROPOSED => [self::PROPOSED, self::REFUSED],
        ],
    ];

    public function canTransition(string $from, string $to, string $actor): bool
    {
        return in_array($to, self::TRANSITIONS[$actor][$from] ?? [], true);
    }

    /**
     * Statuts encore soumis à la date de validité.
     *
     * @return string[]
     */
    public function getExpirable(): array
    {
        return [self::PROPOSED, self::ACCEPTED];
    }

    /**
     * Filtre « En attente » de la maquette : demande envoyée ou proposition à étudier.
     */
    public function isWaiting(string $status): bool
    {
        return in_array($status, [self::REQUESTED, self::PROPOSED], true);
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public function getLabels(): array
    {
        return [
            self::DRAFT => ['label' => (string) __('Brouillon'), 'tone' => 'neutral'],
            self::REQUESTED => ['label' => (string) __('Demande envoyée'), 'tone' => 'warning'],
            self::PROPOSED => ['label' => (string) __('En attente'), 'tone' => 'warning'],
            self::ACCEPTED => ['label' => (string) __('Accepté'), 'tone' => 'success'],
            self::ORDERED => ['label' => (string) __('Commandé'), 'tone' => 'info'],
            self::REFUSED => ['label' => (string) __('Refusé'), 'tone' => 'danger'],
            self::CANCELED => ['label' => (string) __('Annulé'), 'tone' => 'neutral'],
            self::EXPIRED => ['label' => (string) __('Expiré'), 'tone' => 'neutral'],
        ];
    }

    /**
     * @return array{label: string, tone: string}
     */
    public function getLabel(string $status): array
    {
        return $this->getLabels()[$status] ?? ['label' => $status, 'tone' => 'neutral'];
    }
}

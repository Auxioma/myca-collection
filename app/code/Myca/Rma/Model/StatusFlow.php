<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

/**
 * États d'une demande (cahier des charges) : créée › examinée › autorisée › en transit › réceptionnée › contrôlée ›
 * remboursée / échangée › clôturée, plus refusée et annulée. Chaque passage est vérifié ici, quel que soit l'écran.
 */
class StatusFlow
{
    public const CREATED = 'created';
    public const REVIEWING = 'reviewing';
    public const AUTHORIZED = 'authorized';
    public const IN_TRANSIT = 'in_transit';
    public const RECEIVED = 'received';
    public const INSPECTED = 'inspected';
    public const REFUNDED = 'refunded';
    public const EXCHANGED = 'exchanged';
    public const REJECTED = 'rejected';
    public const CANCELED = 'canceled';
    public const CLOSED = 'closed';

    public const ACTOR_REQUESTER = 'requester';
    public const ACTOR_ADMIN = 'admin';

    private const TRANSITIONS = [
        self::CREATED => [self::REVIEWING, self::AUTHORIZED, self::REJECTED, self::CANCELED],
        self::REVIEWING => [self::AUTHORIZED, self::REJECTED, self::CANCELED],
        self::AUTHORIZED => [self::IN_TRANSIT, self::RECEIVED, self::CANCELED],
        self::IN_TRANSIT => [self::RECEIVED],
        self::RECEIVED => [self::INSPECTED],
        self::INSPECTED => [self::REFUNDED, self::EXCHANGED, self::REJECTED, self::CLOSED],
        self::REFUNDED => [self::CLOSED],
        self::EXCHANGED => [self::CLOSED],
        self::REJECTED => [self::CLOSED],
        self::CANCELED => [],
        self::CLOSED => [],
    ];

    /**
     * Le demandeur (client ou boutique) peut seulement annuler sa demande avant l'envoi du colis, puis signaler
     * qu'il l'a remis au transporteur. Le reste relève de MYCA.
     */
    private const REQUESTER_TRANSITIONS = [
        self::CREATED => [self::CANCELED],
        self::REVIEWING => [self::CANCELED],
        self::AUTHORIZED => [self::IN_TRANSIT, self::CANCELED],
    ];

    /**
     * Le remboursement passe par RmaManagement::refund(), qui émet l'avoir : il n'est pas un simple changement d'état.
     */
    private const SYSTEM_ONLY = [self::REFUNDED];

    /**
     * @return string[]
     */
    public function getAllowedTransitions(string $from, string $actor): array
    {
        $next = $actor === self::ACTOR_REQUESTER
            ? (self::REQUESTER_TRANSITIONS[$from] ?? [])
            : array_values(array_diff(self::TRANSITIONS[$from] ?? [], self::SYSTEM_ONLY));

        return $next;
    }

    public function canTransition(string $from, string $to, string $actor): bool
    {
        return in_array($to, $this->getAllowedTransitions($from, $actor), true);
    }

    public function canRefund(string $status): bool
    {
        return in_array(self::REFUNDED, self::TRANSITIONS[$status] ?? [], true);
    }

    public function isOpen(string $status): bool
    {
        return !in_array($status, [self::REJECTED, self::CANCELED, self::CLOSED], true);
    }

    /**
     * Une demande annulée ou refusée libère ses quantités ; les autres les gardent, même clôturées.
     */
    public function holdsQuantities(string $status): bool
    {
        return !in_array($status, [self::REJECTED, self::CANCELED], true);
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public function getLabels(): array
    {
        return [
            self::CREATED => ['label' => (string) __('Demande enregistrée'), 'tone' => 'info'],
            self::REVIEWING => ['label' => (string) __('Analyse en cours'), 'tone' => 'warning'],
            self::AUTHORIZED => ['label' => (string) __('Retour autorisé'), 'tone' => 'info'],
            self::IN_TRANSIT => ['label' => (string) __('Colis en transit'), 'tone' => 'info'],
            self::RECEIVED => ['label' => (string) __('Colis réceptionné'), 'tone' => 'warning'],
            self::INSPECTED => ['label' => (string) __('Articles contrôlés'), 'tone' => 'warning'],
            self::REFUNDED => ['label' => (string) __('Remboursée'), 'tone' => 'warning'],
            self::EXCHANGED => ['label' => (string) __('Échange expédié'), 'tone' => 'warning'],
            self::REJECTED => ['label' => (string) __('Refusée'), 'tone' => 'danger'],
            self::CANCELED => ['label' => (string) __('Annulée'), 'tone' => 'neutral'],
            self::CLOSED => ['label' => (string) __('Clôturée'), 'tone' => 'success'],
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

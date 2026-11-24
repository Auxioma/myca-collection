<?php

declare(strict_types=1);

namespace Myca\Production\Model;

/**
 * Règles du cahier des charges « Production et fournisseurs » et « Contrôle qualité », sans accès à la base.
 */
class ProductionRules
{
    public const PLANNED = 'planned';
    public const COMMITTED = 'committed';
    public const IN_PRODUCTION = 'in_production';
    public const RECEIVING = 'receiving';
    public const RECEIVED = 'received';
    public const CANCELED = 'canceled';

    public const ACCEPTED = 'accepted';
    public const QUARANTINE = 'quarantine';
    public const REFUSED = 'refused';

    public const MILESTONES = ['sample', 'pattern', 'embroidery', 'colors', 'quality', 'launch'];
    public const CHECKLIST = ['material', 'color', 'dimensions', 'logos', 'embroidery', 'labels', 'packaging'];

    private const TRANSITIONS = [
        self::PLANNED => [self::COMMITTED, self::CANCELED],
        self::COMMITTED => [self::IN_PRODUCTION, self::CANCELED],
        self::IN_PRODUCTION => [self::RECEIVING],
        self::RECEIVING => [self::RECEIVED],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Statuts où les quantités sont encore prévisionnelles (modifiables) ; après l'engagement, elles sont figées.
     */
    public function isEditable(string $status): bool
    {
        return $status === self::PLANNED;
    }

    public function canReceive(string $status): bool
    {
        return in_array($status, [self::IN_PRODUCTION, self::RECEIVING], true);
    }

    /**
     * Couleurs sous le minimum de commande (MOQ par modèle et couleur).
     *
     * @param array<int, array<string, mixed>> $lines lignes avec color et qty_planned
     * @return array<string, int> couleur → quantité commandée, pour les couleurs en dessous du MOQ
     */
    public function getMoqShortfalls(array $lines, int $moqPerColor): array
    {
        if ($moqPerColor <= 0) {
            return [];
        }
        $byColor = [];
        foreach ($lines as $line) {
            $color = (string) ($line['color'] ?? '') ?: '—';
            $byColor[$color] = ($byColor[$color] ?? 0) + (int) $line['qty_planned'];
        }

        return array_filter($byColor, static fn (int $qty) => $qty > 0 && $qty < $moqPerColor);
    }

    /**
     * Raisons qui empêchent d'engager l'ordre auprès de l'usine (vide si possible).
     *
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $lines lignes avec color et qty_planned
     * @return string[]
     */
    public function getCommitBlockers(array $order, array $lines): array
    {
        $blockers = [];
        if (array_sum(array_map(static fn (array $line) => (int) $line['qty_planned'], $lines)) <= 0) {
            $blockers[] = (string) __('Aucune quantité n’est prévue.');
        }
        if (empty($order['tolerances_validated_at'])) {
            $blockers[] = (string) __('Les tolérances et seuils de défauts doivent être validés avant fabrication.');
        }
        foreach ($this->getMoqShortfalls($lines, (int) ($order['moq_per_color'] ?? 0)) as $color => $qty) {
            $blockers[] = (string) __('Couleur %1 : %2 pièces, sous le minimum de commande (%3).', $color, $qty, (int) $order['moq_per_color']);
        }
        if ((float) ($order['unit_factory_price'] ?? 0) <= 0) {
            $blockers[] = (string) __('Le prix usine n’est pas renseigné.');
        }

        return $blockers;
    }

    /**
     * Décision possible au vu du contrôle : « accepté » exige une checklist conforme et des défauts dans les seuils
     * validés. Un défaut critique au-delà du seuil interdit aussi la quarantaine (refus).
     *
     * @param array<string, string> $checklist point → ok / nok
     * @param array<string, int> $defects nombre de défauts critical, major, minor
     * @param array<string, int> $limits seuils validés critical, major, minor
     * @return string[] décisions autorisées
     */
    public function getAllowedDecisions(array $checklist, array $defects, array $limits): array
    {
        $checklistOk = true;
        foreach (self::CHECKLIST as $point) {
            if (($checklist[$point] ?? '') !== 'ok') {
                $checklistOk = false;
            }
        }
        if (($defects['critical'] ?? 0) > ($limits['critical'] ?? 0)) {
            return [self::REFUSED];
        }
        $withinLimits = ($defects['major'] ?? 0) <= ($limits['major'] ?? 0) && ($defects['minor'] ?? 0) <= ($limits['minor'] ?? 0);

        return $checklistOk && $withinLimits
            ? [self::ACCEPTED, self::QUARANTINE, self::REFUSED]
            : [self::QUARANTINE, self::REFUSED];
    }

    /**
     * Coût rendu entrepôt par pièce : prix usine + frais (transport, douane…) répartis sur les pièces acceptées.
     */
    public function getLandedUnitCost(float $unitFactoryPrice, float $extraCosts, int $qtyAccepted): ?float
    {
        return $qtyAccepted > 0 ? round($unitFactoryPrice + $extraCosts / $qtyAccepted, 2) : null;
    }

    /**
     * Composition saisie « Coton=95, Élasthanne=5 » : chaque part entre 0 et 100, total 100 %.
     *
     * @return array<string, float>|null null si invalide
     */
    public function parseComposition(string $input): ?array
    {
        $parts = [];
        foreach (array_filter(array_map('trim', preg_split('/[,;\n]/', $input) ?: [])) as $part) {
            if (!preg_match('/^(.+?)\s*[=:]\s*(\d+(?:[.,]\d+)?)\s*%?$/u', $part, $match)) {
                return null;
            }
            $share = (float) str_replace(',', '.', $match[2]);
            if ($share <= 0 || $share > 100) {
                return null;
            }
            $parts[trim($match[1])] = $share;
        }

        return $parts && abs(array_sum($parts) - 100) < 0.01 ? $parts : null;
    }

    /**
     * Grille de mesures saisie ligne par ligne : « S; 96; 68; 42 » (taille ; poitrine ; longueur ; épaules, en cm).
     *
     * @return array<string, array<int, float>>|null
     */
    public function parseSizeChart(string $input): ?array
    {
        $chart = [];
        foreach (array_filter(array_map('trim', explode("\n", $input))) as $row) {
            $cells = array_map('trim', explode(';', $row));
            $size = (string) array_shift($cells);
            if ($size === '' || !$cells) {
                return null;
            }
            $values = [];
            foreach ($cells as $cell) {
                $cell = str_replace(',', '.', $cell);
                if (!is_numeric($cell) || (float) $cell <= 0) {
                    return null;
                }
                $values[] = (float) $cell;
            }
            $chart[$size] = $values;
        }

        return $chart;
    }
}

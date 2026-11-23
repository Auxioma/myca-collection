<?php

declare(strict_types=1);

namespace Myca\Rma\Model;

/**
 * Motifs proposés au demandeur. Un défaut ou une non-conformité relève de la garantie, pas d'un changement d'avis.
 */
class Reasons
{
    public const WARRANTY_REASONS = ['defect', 'damaged', 'not_as_described', 'wrong_item'];

    /**
     * @return array<string, string>
     */
    public function getOptions(string $type): array
    {
        $all = [
            'size' => (string) __('Taille non adaptée'),
            'color' => (string) __('Coloris différent de celui attendu'),
            'changed_mind' => (string) __('L’article ne me convient pas'),
            'not_as_described' => (string) __('Article non conforme à la description'),
            'wrong_item' => (string) __('Erreur d’article'),
            'damaged' => (string) __('Article endommagé à la livraison'),
            'defect' => (string) __('Défaut de fabrication'),
            'other' => (string) __('Autre motif'),
        ];

        return $type === ReturnPolicy::TYPE_WARRANTY
            ? array_intersect_key($all, array_flip(self::WARRANTY_REASONS))
            : $all;
    }

    public function getLabel(string $code): string
    {
        return $this->getOptions(ReturnPolicy::TYPE_RETURN)[$code] ?? $code;
    }

    /**
     * @return array<string, string>
     */
    public function getTypeLabels(): array
    {
        return [
            ReturnPolicy::TYPE_RETURN => (string) __('Retour'),
            ReturnPolicy::TYPE_EXCHANGE => (string) __('Échange'),
            ReturnPolicy::TYPE_WARRANTY => (string) __('SAV'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getOriginLabels(): array
    {
        return [
            ReturnPolicy::ORIGIN_ONLINE => (string) __('Achat MYCA en ligne'),
            ReturnPolicy::ORIGIN_DROPSHIP => (string) __('Dropshipping partenaire'),
            ReturnPolicy::ORIGIN_STORE => (string) __('Stock boutique physique'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getConditionLabels(): array
    {
        return [
            'resellable' => (string) __('Revendable (remis en stock)'),
            'defective' => (string) __('Défectueux (quarantaine)'),
            'scrap' => (string) __('Rebut'),
        ];
    }
}

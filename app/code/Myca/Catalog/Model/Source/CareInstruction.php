<?php

declare(strict_types=1);

namespace Myca\Catalog\Model\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * Consignes d'entretien (onglet « Matières & entretien ») : code stable => libellé. Chaque code a son symbole
 * dans le thème (web/svg/care-<code>.svg). Ajouter une consigne : un code ici et son symbole.
 */
class CareInstruction extends AbstractSource
{
    public const OPTIONS = [
        'wash-30-delicate' => 'Lavage en machine à 30 °C (programme délicat)',
        'wash-30' => 'Lavage en machine à 30 °C',
        'wash-40' => 'Lavage en machine à 40 °C',
        'hand-wash' => 'Lavage à la main',
        'no-bleach' => 'Ne pas utiliser de javel',
        'no-tumble-dry' => 'Ne pas passer au sèche-linge',
        'iron-low' => 'Repassage à basse température (max. 110 °C), sur l’envers',
        'iron-medium' => 'Repassage à température moyenne (max. 150 °C)',
        'no-iron' => 'Ne pas repasser',
        'no-dry-clean' => 'Ne pas nettoyer à sec',
        'dry-clean' => 'Nettoyage à sec professionnel',
        'line-dry' => 'Séchage à l’air libre, sur cintre',
        'flat-dry' => 'Séchage à plat',
    ];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [];
            foreach (self::OPTIONS as $code => $label) {
                $this->_options[] = ['value' => $code, 'label' => (string) __($label)];
            }
        }

        return $this->_options;
    }
}

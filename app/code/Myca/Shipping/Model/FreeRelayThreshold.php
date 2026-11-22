<?php

declare(strict_types=1);

namespace Myca\Shipping\Model;

use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Règle « point relais offert dès 100 € TTC » : le seuil porte sur les produits éligibles, après remise
 * et hors frais de livraison (cahier des charges, règles métier).
 */
class FreeRelayThreshold
{
    public const ELIGIBLE_ATTRIBUTE = 'myca_relay_eligible';

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Montant TTC des lignes éligibles, remises déduites. Les lignes enfants (variantes) sont ignorées :
     * leur montant est porté par la ligne parente.
     *
     * @param iterable<AbstractItem> $items
     */
    public function getEligibleAmount(iterable $items): float
    {
        $amount = 0.0;
        foreach ($items as $item) {
            /** @var AbstractItem|null $parent La signature Magento annonce un objet, mais une ligne racine n'en a pas. */
            $parent = $item->getParentItem();
            if ($parent !== null || !$this->isEligible($item)) {
                continue;
            }
            $amount += (float) $item->getRowTotalInclTax() - (float) $item->getDiscountAmount();
        }

        return round(max(0.0, $amount), 2);
    }

    public function isReached(float $eligibleAmount, ?int $storeId = null): bool
    {
        $threshold = $this->config->getFreeRelayThreshold($storeId);

        return $threshold > 0 && round($eligibleAmount, 2) >= round($threshold, 2);
    }

    public function getRemaining(float $eligibleAmount, ?int $storeId = null): float
    {
        return round(max(0.0, $this->config->getFreeRelayThreshold($storeId) - $eligibleAmount), 2);
    }

    private function isEligible(AbstractItem $item): bool
    {
        $value = $item->getProduct()->getData(self::ELIGIBLE_ATTRIBUTE);

        // Sans valeur enregistrée, un produit est éligible (valeur par défaut de l'attribut).
        return $value === null || $value === '' || (bool) $value;
    }
}

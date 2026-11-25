<?php

declare(strict_types=1);

namespace Myca\Dropship\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Réglages des commandes partenaires (Magasins > Configuration > MYCA > Commandes partenaires).
 * Les valeurs par défaut sont neutres (aucune remise, aucun minimum) tant que la grille tarifaire n'est pas validée.
 */
class OrderConfig
{
    private const PREFIX = 'myca_partner_order/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    public function getDropshipDiscount(): float
    {
        return (float) $this->value('pricing/dropship_discount');
    }

    /**
     * @return array<int, array{min_qty: int, discount: float}> paliers de l'achat de stock (quantité par modèle)
     */
    public function getStockTiers(): array
    {
        $tiers = [];
        foreach ($this->rows('pricing/stock_tiers') as $row) {
            $tiers[] = ['min_qty' => max(1, (int) ($row['min_qty'] ?? 1)), 'discount' => (float) ($row['discount'] ?? 0)];
        }

        return $tiers;
    }

    /**
     * Minimum par modèle et par couleur en achat de stock (0 = aucun).
     */
    public function getStockMinimumPerColor(): int
    {
        return max(0, (int) $this->value('pricing/stock_moq_per_color'));
    }

    public function getShippingPrice(string $mode): float
    {
        return (float) $this->value('shipping/' . ($mode === PartnerPricing::MODE_STOCK ? 'stock_price' : 'dropship_price'));
    }

    /**
     * Achat de stock : port offert à partir de ce montant de produits (0 = jamais).
     */
    public function getStockFreeShippingFrom(): float
    {
        return (float) $this->value('shipping/stock_free_from');
    }

    /**
     * @return string[] moyens de paiement proposés aux boutiques
     */
    public function getPartnerPaymentMethods(): array
    {
        return $this->list('payment/partner_methods');
    }

    /**
     * @return string[] moyens réservés aux boutiques (masqués aux particuliers)
     */
    public function getPartnerOnlyPaymentMethods(): array
    {
        return $this->list('payment/partner_only_methods');
    }

    private function value(string $path): mixed
    {
        return $this->scopeConfig->getValue(self::PREFIX . $path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string[]
     */
    private function list(string $path): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->value($path)))));
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function rows(string $path): array
    {
        $value = $this->value($path);
        if (is_array($value)) {
            return $value;
        }
        $rows = $value ? $this->json->unserialize((string) $value) : [];

        return is_array($rows) ? $rows : [];
    }
}

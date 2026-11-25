<?php

declare(strict_types=1);

namespace Myca\Core\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Réglages MYCA du module officiel Stripe : libellé français, logos des cartes acceptées, paiement intégré au
 * tunnel (Payment Element) et débit à la commande. Le mode test n'est posé que si aucun mode n'est choisi ; les clés
 * (test puis live) se saisissent dans Magasins > Configuration > Ventes > Moyens de paiement > Stripe.
 */
class ConfigureStripe implements DataPatchInterface
{
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function apply(): self
    {
        $this->configWriter->save('payment/stripe_payments/title', 'Carte bancaire');
        $this->configWriter->save('payment/stripe_payments/payment_action', 'authorize_capture');
        $this->configWriter->save('payment/stripe_payments/payment_flow', '0');
        $this->configWriter->save('payment/stripe_payments/card_icons', '1');
        $this->configWriter->save('payment/stripe_payments/card_icons_specific', 'cartes_bancaires,visa,mastercard');
        $this->configWriter->save('payment/stripe_payments/save_payment_method', '0');
        if (!$this->scopeConfig->getValue('payment/stripe_payments_basic/stripe_mode')) {
            $this->configWriter->save('payment/stripe_payments_basic/stripe_mode', 'test');
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}

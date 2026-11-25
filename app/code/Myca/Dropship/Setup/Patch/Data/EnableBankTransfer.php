<?php

declare(strict_types=1);

namespace Myca\Dropship\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Virement bancaire activé pour les boutiques partenaires (réservé aux partenaires par Myca_Dropship) : la commande
 * est préparée à réception du virement. Les coordonnées bancaires de MYCA restent à saisir dans les instructions.
 */
class EnableBankTransfer implements DataPatchInterface
{
    public function __construct(private readonly WriterInterface $configWriter)
    {
    }

    public function apply(): self
    {
        $this->configWriter->save('payment/banktransfer/active', '1');
        $this->configWriter->save('payment/banktransfer/title', 'Virement bancaire');
        $this->configWriter->save(
            'payment/banktransfer/instructions',
            'Votre commande sera préparée dès réception du virement. Coordonnées bancaires de MYCA : [À COMPLÉTER : IBAN et BIC]. Indiquez le numéro de commande en référence.'
        );

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

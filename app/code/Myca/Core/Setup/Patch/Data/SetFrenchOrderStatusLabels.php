<?php

declare(strict_types=1);

namespace Myca\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Libellés français des statuts de commande, alignés sur l'espace client (M28, M29) et les e-mails.
 */
class SetFrenchOrderStatusLabels implements DataPatchInterface
{
    private const LABELS = [
        'pending' => 'En attente',
        'processing' => 'En préparation',
        'complete' => 'Expédiée',
        'canceled' => 'Annulée',
        'closed' => 'Remboursée',
        'holded' => 'En attente',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('sales_order_status');
        foreach (self::LABELS as $status => $label) {
            $connection->update($table, ['label' => $label], ['status = ?' => $status]);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddDeliveredOrderStatus::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}

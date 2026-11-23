<?php

declare(strict_types=1);

namespace Myca\Core\Setup\Patch\Data;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Statut « Livrée » : Magento s'arrête à « Terminée » (facturée et expédiée). La livraison confirmée
 * par le transporteur passe la commande à ce statut (dernière étape du suivi M28 / M29).
 */
class AddDeliveredOrderStatus implements DataPatchInterface
{
    public const STATUS = 'myca_delivered';

    public function __construct(
        private readonly StatusFactory $statusFactory,
        private readonly StatusResource $statusResource
    ) {
    }

    public function apply(): self
    {
        $status = $this->statusFactory->create();
        $status->setData(['status' => self::STATUS, 'label' => 'Livrée']);
        $this->statusResource->save($status);
        $status->assignState(Order::STATE_COMPLETE, false, true);

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

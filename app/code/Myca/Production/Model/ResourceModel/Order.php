<?php

declare(strict_types=1);

namespace Myca\Production\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Lecture pour la grille d'administration. Les écritures passent par Model\ProductionManagement.
 */
class Order extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_production_order', 'order_id');
    }
}

<?php

declare(strict_types=1);

namespace Myca\Passport\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Lecture des lots pour la grille d'administration. Les écritures passent par Model\PassportManagement.
 */
class Lot extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_passport_lot', 'lot_id');
    }
}

<?php

declare(strict_types=1);

namespace Myca\Loyalty\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Ressource de lecture du journal pour la grille d'administration. Les écritures passent par Model\Ledger.
 */
class Transaction extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_loyalty_transaction', 'transaction_id');
    }
}

<?php

declare(strict_types=1);

namespace Myca\Core\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Table du journal d'audit (lecture par la grille d'administration ; écriture par Myca\Core\Model\AuditLogger).
 */
class AuditLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_audit_log', 'log_id');
    }
}

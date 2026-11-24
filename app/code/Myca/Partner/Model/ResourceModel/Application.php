<?php

declare(strict_types=1);

namespace Myca\Partner\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Lecture des candidatures pour la grille d'administration. Les écritures passent par Model\PartnerManagement.
 */
class Application extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('myca_partner_application', 'application_id');
    }
}

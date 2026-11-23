<?php

declare(strict_types=1);

namespace Myca\Partner\Model\ResourceModel\Partner;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Myca\Partner\Model\Partner;
use Myca\Partner\Model\ResourceModel\Partner as PartnerResource;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'partner_id';

    protected function _construct(): void
    {
        $this->_init(Partner::class, PartnerResource::class);
    }
}

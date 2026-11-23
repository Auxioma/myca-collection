<?php

declare(strict_types=1);

namespace Myca\Rma\Model\ResourceModel\Rma;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;
use Myca\Rma\Model\Rma;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rma_id';

    protected function _construct(): void
    {
        $this->_init(Rma::class, RmaResource::class);
    }
}

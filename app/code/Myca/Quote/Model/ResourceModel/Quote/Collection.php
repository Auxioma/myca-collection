<?php

declare(strict_types=1);

namespace Myca\Quote\Model\ResourceModel\Quote;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Myca\Quote\Model\Quote;
use Myca\Quote\Model\ResourceModel\Quote as QuoteResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'quote_id';

    protected function _construct(): void
    {
        $this->_init(Quote::class, QuoteResource::class);
    }
}

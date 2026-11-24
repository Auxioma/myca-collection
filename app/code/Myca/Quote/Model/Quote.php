<?php

declare(strict_types=1);

namespace Myca\Quote\Model;

use Magento\Framework\Model\AbstractModel;
use Myca\Quote\Model\ResourceModel\Quote as QuoteResource;

/**
 * Devis MYCA (table myca_quote). Il ne se modifie que par QuoteManagement.
 */
class Quote extends AbstractModel
{
    protected $_eventPrefix = 'myca_quote';

    protected function _construct(): void
    {
        $this->_init(QuoteResource::class);
    }

    public function getIncrementId(): string
    {
        return (string) $this->getData('increment_id');
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    public function getCustomerId(): int
    {
        return (int) $this->getData('customer_id');
    }
}

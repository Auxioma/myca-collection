<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Model\ResourceModel\Country;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\TwoFactorAuth\Model\ResourceModel\Country;

/**
 * Country codes collection
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class Collection extends AbstractCollection
{
    protected $_idFieldName = 'country_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \Magento\TwoFactorAuth\Model\Country::class,
            Country::class
        );
    }
}

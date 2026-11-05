<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace PayPal\Braintree\Model\Adminhtml\Source;

use PayPal\Braintree\Model\Lpm\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Provide options for backend config.
 */
class LpmMethods implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::VALUE_BANCONTACT, 'label' => __('Bancontact')],
            ['value' => Config::VALUE_EPS, 'label' => __('EPS')],
            ['value' => Config::VALUE_IDEAL, 'label' => __('iDEAL')],
            ['value' => Config::VALUE_MYBANK, 'label' => __('MyBank')],
            ['value' => Config::VALUE_P24, 'label' => __('P24')],
            ['value' => Config::VALUE_SEPA, 'label' => __('SEPA/ELV Direct Debit')],
            ['value' => Config::VALUE_PAY_UPON_INVOICE, 'label' => __('Pay Upon Invoice')],
            ['value' => Config::VALUE_BLIK, 'label' => __('BLIK')]
        ];
    }
}

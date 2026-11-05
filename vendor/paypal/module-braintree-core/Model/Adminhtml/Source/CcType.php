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

/** @codeCoverageIgnore
 */
class CcType extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @inheritDoc
     */
    public function getAllowedTypes(): array
    {
        return ['VI', 'MC', 'AE', 'DI', 'JCB', 'MI', 'DN', 'UPD', 'ELO'];
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $allowed = $this->getAllowedTypes();
        $options = [];

        foreach ($this->_paymentConfig->getCcTypes() as $code => $name) {
            if (in_array($code, $allowed)) {
                $options[] = ['value' => $code, 'label' => $name];
            }
        }

        return $options;
    }
}

<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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

namespace PayPal\Braintree\Plugin\Payment\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use PayPal\Braintree\Model\Lpm\Config;

class MethodPlugin
{
    /**
     * Update payment action for PUI Lpm if needed
     *
     * @param MethodInterface $subject
     * @param string|null $result
     * @return string|null
     * @throws LocalizedException
     */
    public function afterGetConfigPaymentAction(MethodInterface $subject, ?string $result): ?string
    {
        $methodCode = $subject->getCode();
        $source = $subject->getInfoInstance()->getAdditionalInformation()['fundingSource'] ?? null;
        if ($methodCode === 'braintree_local_payment' && $source === Config::VALUE_PAY_UPON_INVOICE) {
            return '';
        }

        // Return original action for other methods
        return $result;
    }
}

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

namespace Magento\PaymentServicesPaypal\Plugin\Vault;

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Vault\Observer\PaymentTokenAssigner;

/**
 * Preserves Payment Services order data through the vault payment token assignment.
 *
 * PaymentTokenAssigner::execute() wipes all additional information from the quote payment
 * before assigning vault token data. Fields that are not re-supplied via additional_data
 * (e.g. paypal_order_amount, which is server-set and never sent by the client) are lost.
 * This plugin saves and restores those fields around the wipe.
 */
class PaymentTokenAssignerPlugin
{
    private const VAULT_PAYMENT_METHOD_CODE = 'payment_services_paypal_vault';

    private const PRESERVED_FIELDS = [
        'paypal_order_id',
        'paypal_order_amount',
    ];

    /**
     * Plugin to preserve necessary data through the vault token assignment process
     *
     * @param PaymentTokenAssigner $subject
     * @param callable $proceed
     * @param Observer $observer
     * @return void
     */
    public function aroundExecute(
        PaymentTokenAssigner $subject,
        callable $proceed,
        Observer $observer
    ): void {
        $payment = $observer->getEvent()->getDataByKey(AbstractDataAssignObserver::MODEL_CODE);

        if (!$this->shouldRun($payment)) {
            $proceed($observer);
            return;
        }

        $preserved = [];
        foreach (self::PRESERVED_FIELDS as $field) {
            $value = $payment->getAdditionalInformation($field);
            if ($value !== null) {
                $preserved[$field] = $value;
            }
        }

        $proceed($observer);

        foreach ($preserved as $field => $value) {
            $payment->setAdditionalInformation($field, $value);
        }
    }

    /**
     * Check if plugin can be executed
     *
     * @param mixed $payment
     * @return bool
     */
    private function shouldRun(mixed $payment): bool
    {
        return $payment instanceof InfoInterface
            && $payment instanceof PaymentInterface
            && $payment->getMethod() === self::VAULT_PAYMENT_METHOD_CODE;
    }
}

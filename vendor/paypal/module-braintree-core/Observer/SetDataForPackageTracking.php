<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
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

namespace PayPal\Braintree\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Payment;
use PayPal\Braintree\Gateway\Config\PayPal\Config;

class SetDataForPackageTracking implements ObserverInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Set shipment tracking flag to yes if eligible
     *
     * @param Observer $observer
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $track = $observer->getData('data_object');
        $payment = $track->getShipment()->getOrder()->getPayment();
        if ($payment instanceof Payment && !$this->shouldTrack($payment)) {
            return;
        }
        if (!str_contains($track->getDescription() ?? '', '<tracking_sent>')) {
            $track->setData('tracking_flag', true);
        }
        $track->setDescription($track->getDescription() . '<tracking_sent>');
    }

    /**
     * Check whether shipment can be tracked or not
     *
     * @param Payment $payment
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function shouldTrack(Payment $payment): bool
    {
        $paymentMethodCode = (string) $payment->getMethod();

        // Integration tests / core flows without payment method
        if ($paymentMethodCode === '') {
            return true;
        }

        // Payment is Braintree LPM Pay Upon Invoice
        if ($paymentMethodCode === 'braintree_local_payment'
            && !empty($payment->getAdditionalInformation('payment_id'))
        ) {
            return true;
        }

        // Preserve original logic exactly
        return str_contains($paymentMethodCode, 'braintree_paypal')
            && $this->config->isShippingTrackingEnabled();
    }
}

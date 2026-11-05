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
use Magento\Sales\Api\Data\TrackInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Shipment;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Tracking\SendTracking;

class SendPackageTracking implements ObserverInterface
{
    /**
     * @param Config $config
     * @param SendTracking $sendTracking
     */
    public function __construct(
        private readonly Config $config,
        private readonly SendTracking $sendTracking
    ) {
    }

    /**
     * Send shipment tracking information
     *
     * @param Observer $observer
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $track = $observer->getData('data_object');
        $shipment = $track->getShipment();
        $order = $shipment->getOrder();
        $payment = $order->getPayment();
        if ($payment instanceof Payment
            && $payment->getMethod() === 'braintree_local_payment'
            && $payment->getAdditionalInformation('payment_id')
        ) {
            $this->buildAndSend($payment, $shipment, $track);
            return;
        }

        if (!$this->config->isShippingTrackingEnabled()
            || !$order->getInvoiceCollection()->getSize()
            || str_contains($track->getDescription() ?? '', "<sent_to_paypal>")
            || !$track->getData('tracking_flag')
            || !str_contains($order->getPayment()->getMethod(), 'braintree_paypal')) {
            return;
        }

        $this->buildAndSend($payment, $shipment, $track);
    }

    /**
     * Generate tracking information and send to Braintree
     *
     * @param Payment $payment
     * @param Shipment $shipment
     * @param TrackInterface $track
     * @return void
     */
    private function buildAndSend(
        Payment $payment,
        Shipment $shipment,
        TrackInterface $track
    ): void {
        $items = [];
        foreach ($shipment->getItems() as $item) {
            if (!$item->getQty()) {
                continue;
            }
            $items[] = [
                'name' => substr($item->getName(), 0, 127),
                'productCode' => substr($item->getSku(), 0, 127),
                'quantity' => $item->getQty()
            ];
        }

        $this->sendTracking->execute($payment->getLastTransId(), $track, $items);
    }
}

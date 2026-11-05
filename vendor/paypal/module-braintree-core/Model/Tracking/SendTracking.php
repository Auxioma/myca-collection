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

namespace PayPal\Braintree\Model\Tracking;

use Braintree\Transaction;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TrackInterface;
use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use Psr\Log\LoggerInterface;

class SendTracking
{
    /**
     * @param Config $config
     * @param BraintreeAdapter $braintreeAdapter
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly BraintreeAdapter $braintreeAdapter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get track data and send
     *
     * @param string $transactionId
     * @param TrackInterface $track
     * @param array $items
     * @return void
     * @throws LocalizedException
     */
    public function execute(string $transactionId, TrackInterface $track, array $items): void
    {
        $trackData = $this->getTrackData($track);
        $trackData['lineItems'] = $items;
        try {
            Transaction::packageTracking($transactionId, $trackData);
        } catch (Exception $e) {
            $this->logger->error(
                'Error response from package tracking request',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Build an array of tracking data for tracking number
     *
     * @param TrackInterface $track
     * @return array
     * @throws LocalizedException
     */
    private function getTrackData(TrackInterface $track): array
    {
        $notifyPayer = $this->config->notifyPayer();
        $carrier = $track->getTitle() ?: $track->getCarrierCode();

        return [
            'trackingNumber' => $track->getTrackNumber(),
            'carrier' => $carrier,
            'notifyPayer' => $notifyPayer
        ];
    }
}

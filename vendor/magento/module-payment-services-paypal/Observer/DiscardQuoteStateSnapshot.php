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

namespace Magento\PaymentServicesPaypal\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;

class DiscardQuoteStateSnapshot implements ObserverInterface
{
    /**
     * Remove internal SSSC recovery data before quote-to-order conversion is persisted.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $event->getQuote()?->getPayment()?->unsAdditionalInformation(QuoteStateManager::SNAPSHOT_KEY);
        $event->getQuote()?->getPayment()?->unsAdditionalInformation(QuoteStateManager::SESSION_ID_KEY);
        $event->getOrder()?->getPayment()?->unsAdditionalInformation(QuoteStateManager::SNAPSHOT_KEY);
        $event->getOrder()?->getPayment()?->unsAdditionalInformation(QuoteStateManager::SESSION_ID_KEY);
    }
}

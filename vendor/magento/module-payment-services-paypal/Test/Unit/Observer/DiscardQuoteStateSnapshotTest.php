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

namespace Magento\PaymentServicesPaypal\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteStateManager;
use Magento\PaymentServicesPaypal\Observer\DiscardQuoteStateSnapshot;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class DiscardQuoteStateSnapshotTest extends TestCase
{
    public function testExecuteRemovesInternalStateFromQuoteAndOrderPayments(): void
    {
        $quotePayment = $this->createMock(QuotePayment::class);
        $orderPayment = $this->createMock(InfoInterface::class);
        $quote = $this->createMock(Quote::class);
        $order = $this->createMock(Order::class);
        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getQuote', 'getOrder'])
            ->getMock();
        $observer = $this->createMock(Observer::class);

        $quote->method('getPayment')->willReturn($quotePayment);
        $order->method('getPayment')->willReturn($orderPayment);
        $event->method('getQuote')->willReturn($quote);
        $event->method('getOrder')->willReturn($order);
        $observer->method('getEvent')->willReturn($event);

        $quoteFields = [];
        $orderFields = [];
        $quotePayment->expects($this->exactly(2))->method('unsAdditionalInformation')
            ->willReturnCallback(static function (string $field) use (&$quoteFields, $quotePayment): QuotePayment {
                $quoteFields[] = $field;
                return $quotePayment;
            });
        $orderPayment->expects($this->exactly(2))->method('unsAdditionalInformation')
            ->willReturnCallback(static function (string $field) use (&$orderFields, $orderPayment): InfoInterface {
                $orderFields[] = $field;
                return $orderPayment;
            });

        (new DiscardQuoteStateSnapshot())->execute($observer);

        $this->assertSame(
            [QuoteStateManager::SNAPSHOT_KEY, QuoteStateManager::SESSION_ID_KEY],
            $quoteFields
        );
        $this->assertSame(
            [QuoteStateManager::SNAPSHOT_KEY, QuoteStateManager::SESSION_ID_KEY],
            $orderFields
        );
    }
}

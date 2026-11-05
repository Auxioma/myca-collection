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

namespace Magento\PaymentServicesPaypal\Test\Unit\Plugin\CustomCapture;

use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\PaymentServicesPaypal\Plugin\CustomCapture\InvoiceItemsPlugin;
use Magento\Sales\Block\Adminhtml\Items\AbstractItems;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\TestCase;

class InvoiceItemsPluginTest extends TestCase
{
    public function testReturnsPreparedInvoiceWithoutCallingOriginalMethod(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $invoiceContext = new InvoiceContext();
        $invoiceContext->setInvoice($invoice);
        $plugin = new InvoiceItemsPlugin($invoiceContext);
        $proceed = function (): void {
            self::fail('The original invoice lookup must not be called.');
        };

        self::assertSame(
            $invoice,
            $plugin->aroundGetInvoice($this->createMock(AbstractItems::class), $proceed)
        );
    }

    public function testUsesOriginalMethodWhenNoPreparedInvoiceExists(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $plugin = new InvoiceItemsPlugin(new InvoiceContext());
        $proceed = static fn (): Invoice => $invoice;

        self::assertSame(
            $invoice,
            $plugin->aroundGetInvoice($this->createMock(AbstractItems::class), $proceed)
        );
    }
}

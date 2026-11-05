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

namespace Magento\PaymentServicesPaypal\Test\Unit\Config;

use DOMDocument;
use DOMXPath;
use Magento\PaymentServicesPaypal\Plugin\CustomCapture\InvoiceTotalsReasonPlugin;
use PHPUnit\Framework\TestCase;

class CustomerInvoiceTotalsReasonPluginConfigTest extends TestCase
{
    public function testCustomerTotalsPluginUsesConcreteClass(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->load(dirname(__DIR__, 3) . '/etc/di.xml'));
        $xpath = new DOMXPath($document);
        $virtualType = '/config/virtualType'
            . '[@name="Magento\\PaymentServicesPaypal\\Plugin\\CustomCapture\\CustomerInvoiceTotalsReasonPlugin"]';

        $this->assertSame(0, $xpath->query($virtualType)->length);
        $this->assertTrue(class_exists(InvoiceTotalsReasonPlugin::class));
        $this->assertSame(InvoiceTotalsReasonPlugin::class, $xpath->evaluate('string(
            /config/type[@name="Magento\\Sales\\Block\\Order\\Invoice\\Totals"]
                /plugin[@name="payment_services_custom_capture_invoice_totals_reason"]/@type
        )'));
    }
}

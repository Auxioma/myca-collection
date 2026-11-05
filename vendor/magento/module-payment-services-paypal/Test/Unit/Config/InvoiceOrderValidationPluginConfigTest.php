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
use Magento\PaymentServicesPaypal\Plugin\CustomCapture\InvoiceOrderValidationPlugin;
use PHPUnit\Framework\TestCase;

class InvoiceOrderValidationPluginConfigTest extends TestCase
{
    public function testRestInvoiceProcessingUsesSingleValidationPlugin(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->load(dirname(__DIR__, 3) . '/etc/di.xml'));
        $xpath = new DOMXPath($document);

        $this->assertTrue(class_exists(InvoiceOrderValidationPlugin::class));
        $this->assertSame(0, $xpath->query(
            '/config/type[@name="Magento\\Sales\\Model\\InvoiceOrder"]'
                . '/plugin[@name="payment_services_custom_capture_invoice_order"]'
        )->length);
        $this->assertSame(0, $xpath->query(
            '/config/type[@name="Magento\\Sales\\Model\\Order\\InvoiceDocumentFactory"]'
                . '/plugin[@name="payment_services_custom_capture_invoice_document_factory"]'
        )->length);
        $this->assertSame(InvoiceOrderValidationPlugin::class, $xpath->evaluate('string(
            /config/type[@name="Magento\\Sales\\Model\\Order\\Validation\\InvoiceOrderInterface"]
                /plugin[@name="payment_services_custom_capture_invoice_order_validation"]/@type
        )'));
    }
}

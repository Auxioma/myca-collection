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

use Magento\Framework\App\Request\Http;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Applier;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Data;
use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Validator;
use Magento\PaymentServicesPaypal\Plugin\CustomCapture\AdminInvoicePreparePlugin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminInvoicePreparePluginTest extends TestCase
{
    /** @var Http|MockObject */
    private Http|MockObject $request;

    /** @var Validator|MockObject */
    private Validator|MockObject $validator;

    /** @var Applier|MockObject */
    private Applier|MockObject $applier;

    /** @var InvoiceContext */
    private InvoiceContext $invoiceContext;

    /** @var AdminInvoicePreparePlugin */
    private AdminInvoicePreparePlugin $plugin;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->validator = $this->createMock(Validator::class);
        $this->applier = $this->createMock(Applier::class);
        $this->invoiceContext = new InvoiceContext();
        $this->plugin = new AdminInvoicePreparePlugin(
            $this->request,
            $this->validator,
            $this->applier,
            $this->invoiceContext
        );
    }

    public function testAfterPrepareInvoiceAppliesCustomCaptureForSupportedAction(): void
    {
        $subject = $this->createMock(InvoiceService::class);
        $invoice = $this->getMockBuilder(Invoice::class)->disableOriginalConstructor()->getMock();
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $data = new Data(30.0, 30.0, '');

        $this->request->method('getFullActionName')->willReturn('sales_order_invoice_save');
        $this->request->method('getParam')->with('invoice', [])->willReturn(
            [
                'custom_capture_amount' => '30',
                'custom_capture_amount_reason' => '',
                'capture_case' => Invoice::CAPTURE_ONLINE,
            ]
        );
        $this->validator->method('isOnlineCaptureCase')->with(Invoice::CAPTURE_ONLINE)->willReturn(true);
        $this->validator->expects($this->once())
            ->method('validateAndCreate')
            ->with('30', '', $order, true)
            ->willReturn($data);
        $this->applier->expects($this->once())->method('apply')->with($invoice, $data);

        $this->assertSame($invoice, $this->plugin->afterPrepareInvoice($subject, $invoice, $order));
        $this->assertSame($invoice, $this->invoiceContext->getInvoice());
    }

    public function testAfterPrepareInvoiceStoresInvoiceForNewAction(): void
    {
        $subject = $this->createMock(InvoiceService::class);
        $invoice = $this->getMockBuilder(Invoice::class)->disableOriginalConstructor()->getMock();
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();

        $this->request->method('getFullActionName')->willReturn('sales_order_invoice_new');
        $this->request->method('getParam')->with('invoice', [])->willReturn([]);
        $this->validator->method('isOnlineCaptureCase')->with(null)->willReturn(false);
        $this->validator->expects($this->once())
            ->method('validateAndCreate')
            ->with(null, null, $order, false)
            ->willReturn(null);
        $this->applier->expects($this->never())->method('apply');

        $this->assertSame($invoice, $this->plugin->afterPrepareInvoice($subject, $invoice, $order));
        $this->assertSame($invoice, $this->invoiceContext->getInvoice());
    }

    public function testAfterPrepareInvoiceSkipsUnsupportedAction(): void
    {
        $subject = $this->createMock(InvoiceService::class);
        $invoice = $this->getMockBuilder(Invoice::class)->disableOriginalConstructor()->getMock();
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();

        $this->request->method('getFullActionName')->willReturn('sales_order_view');
        $this->validator->expects($this->never())->method('validateAndCreate');
        $this->applier->expects($this->never())->method('apply');

        $this->assertSame($invoice, $this->plugin->afterPrepareInvoice($subject, $invoice, $order));
    }
}

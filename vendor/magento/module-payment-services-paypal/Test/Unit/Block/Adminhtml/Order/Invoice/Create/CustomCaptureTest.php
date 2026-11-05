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

namespace Magento\PaymentServicesPaypal\Test\Unit\Block\Adminhtml\Order\Invoice\Create;

use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\PaymentServicesPaypal\Block\Adminhtml\Order\Invoice\Create\CustomCapture;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Data;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Eligibility;
use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomCaptureTest extends TestCase
{
    /**
     * @var ObjectManagerInterface|MockObject
     */
    private ObjectManagerInterface|MockObject $objectManager;

    /**
     * Set up parent block constructor fallbacks.
     */
    protected function setUp(): void
    {
        $jsonHelper = $this->createMock(JsonHelper::class);
        $directoryHelper = $this->createMock(DirectoryHelper::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->objectManager->method('get')->willReturnCallback(
            static function (string $type) use ($jsonHelper, $directoryHelper) {
                return match ($type) {
                    JsonHelper::class => $jsonHelper,
                    DirectoryHelper::class => $directoryHelper,
                    default => null,
                };
            }
        );
        ObjectManager::setInstance($this->objectManager);
    }

    public function testReturnsUnavailableWhenInvoiceIsNotPrepared(): void
    {
        $block = $this->createBlock();

        self::assertFalse($block->isAvailable());
        self::assertSame('', $block->getAmount());
        self::assertSame('', $block->getReason());
    }

    public function testReadsInvoiceFromRequestContext(): void
    {
        $order = $this->createMock(Order::class);
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getData')->willReturnMap([
            [Data::AMOUNT, null, 12.34],
            [Data::REASON, null, 'Additional shipping'],
        ]);

        $invoiceContext = new InvoiceContext();
        $invoiceContext->setInvoice($invoice);

        $eligibility = $this->createMock(Eligibility::class);
        $eligibility->expects(self::once())->method('isEligibleOrder')->with($order)->willReturn(true);

        $block = new CustomCapture($this->createMock(Context::class), $eligibility, $invoiceContext);

        self::assertTrue($block->isAvailable());
        self::assertSame('12.34', $block->getAmount());
        self::assertSame('Additional shipping', $block->getReason());
    }

    private function createBlock(): CustomCapture
    {
        $block = new CustomCapture(
            $this->createMock(Context::class),
            $this->createMock(Eligibility::class),
            new InvoiceContext()
        );

        return $block;
    }
}

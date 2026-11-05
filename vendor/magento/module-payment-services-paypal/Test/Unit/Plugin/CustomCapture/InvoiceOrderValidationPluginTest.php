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

use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Applier;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Data;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Validator;
use Magento\PaymentServicesPaypal\Plugin\CustomCapture\InvoiceOrderValidationPlugin;
use Magento\Sales\Api\Data\InvoiceCreationArgumentsInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Validation\InvoiceOrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InvoiceOrderValidationPluginTest extends TestCase
{
    /** @var Validator|MockObject */
    private Validator|MockObject $validator;

    /** @var Applier|MockObject */
    private Applier|MockObject $applier;

    /** @var InvoiceOrderValidationPlugin */
    private InvoiceOrderValidationPlugin $plugin;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(Validator::class);
        $this->applier = $this->createMock(Applier::class);
        $this->plugin = new InvoiceOrderValidationPlugin($this->validator, $this->applier);
    }

    public function testBeforeValidateAppliesCustomCaptureForOnlineCapture(): void
    {
        $subject = $this->createMock(InvoiceOrderInterface::class);
        $invoice = $this->getMockBuilder(Invoice::class)->disableOriginalConstructor()->getMock();
        $order = $this->createMock(OrderInterface::class);
        $arguments = $this->createArguments('30', 'Additional shipping');
        $data = new Data(30.0, 30.0, 'Additional shipping');

        $this->validator->expects($this->once())
            ->method('validateAndCreate')
            ->with('30', 'Additional shipping', $order, true)
            ->willReturn($data);
        $this->applier->expects($this->once())->method('apply')->with($invoice, $data);

        $this->assertSame(
            [$order, $invoice, true, [], false, false, null, $arguments],
            $this->plugin->beforeValidate($subject, $order, $invoice, true, [], false, false, null, $arguments)
        );
    }

    public function testBeforeValidateUsesOfflineCaptureStateAndDoesNotApplyAfterValidationFailure(): void
    {
        $subject = $this->createMock(InvoiceOrderInterface::class);
        $invoice = $this->getMockBuilder(Invoice::class)->disableOriginalConstructor()->getMock();
        $order = $this->createMock(OrderInterface::class);
        $arguments = $this->createArguments('30', '');

        $this->validator->expects($this->once())
            ->method('validateAndCreate')
            ->with('30', '', $order, false)
            ->willThrowException(new LocalizedException(__('Additional Amount is only available for online capture.')));
        $this->applier->expects($this->never())->method('apply');

        $this->expectException(LocalizedException::class);
        $this->plugin->beforeValidate($subject, $order, $invoice, false, [], false, false, null, $arguments);
    }

    public function testBeforeValidateKeepsInvoiceUntouchedWhenAttributesAreAbsent(): void
    {
        $subject = $this->createMock(InvoiceOrderInterface::class);
        $invoice = $this->getMockBuilder(Invoice::class)->disableOriginalConstructor()->getMock();
        $order = $this->createMock(OrderInterface::class);
        $arguments = $this->createArguments(null, null);

        $this->validator->expects($this->never())->method('validateAndCreate');
        $this->applier->expects($this->never())->method('apply');

        $this->assertSame(
            [$order, $invoice, true, [], false, false, null, $arguments],
            $this->plugin->beforeValidate($subject, $order, $invoice, true, [], false, false, null, $arguments)
        );
    }

    public function testBeforeValidateDoesNotApplyWhenValidatorReturnsNull(): void
    {
        $subject = $this->createMock(InvoiceOrderInterface::class);
        $invoice = $this->getMockBuilder(Invoice::class)->disableOriginalConstructor()->getMock();
        $order = $this->createMock(OrderInterface::class);
        $arguments = $this->createArguments('30', '');

        $this->validator->expects($this->once())
            ->method('validateAndCreate')
            ->with('30', '', $order, true)
            ->willReturn(null);
        $this->applier->expects($this->never())->method('apply');

        $this->plugin->beforeValidate($subject, $order, $invoice, true, [], false, false, null, $arguments);
    }

    public function testBeforeValidateKeepsNonConcreteInvoiceUntouched(): void
    {
        $subject = $this->createMock(InvoiceOrderInterface::class);
        $invoice = $this->createMock(InvoiceInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $arguments = $this->createArguments('30', '');

        $this->validator->expects($this->never())->method('validateAndCreate');
        $this->applier->expects($this->never())->method('apply');

        $this->assertSame(
            [$order, $invoice, true, [], false, false, null, $arguments],
            $this->plugin->beforeValidate($subject, $order, $invoice, true, [], false, false, null, $arguments)
        );
    }

    private function createArguments(mixed $amount, mixed $reason): InvoiceCreationArgumentsInterface
    {
        $extensionAttributes = new class ($amount, $reason) {
            public function __construct(private mixed $amount, private mixed $reason)
            {
            }

            public function getCustomCaptureAmount(): mixed
            {
                return $this->amount;
            }

            public function getCustomCaptureAmountReason(): mixed
            {
                return $this->reason;
            }
        };

        $arguments = $this->createMock(InvoiceCreationArgumentsInterface::class);
        $arguments->method('getExtensionAttributes')->willReturn($extensionAttributes);

        return $arguments;
    }
}

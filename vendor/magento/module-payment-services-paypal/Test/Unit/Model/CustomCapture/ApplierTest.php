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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\CustomCapture;

use LogicException;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Applier;
use Magento\PaymentServicesPaypal\Model\CustomCapture\CommentFormatter;
use Magento\PaymentServicesPaypal\Model\CustomCapture\Data;
use Magento\PaymentServicesPaypal\Model\CustomCapture\TransactionMetadata;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApplierTest extends TestCase
{
    /**
     * @var Applier
     */
    private Applier $applier;

    /**
     * @var CommentFormatter|MockObject
     */
    private CommentFormatter|MockObject $commentFormatter;

    /**
     * @var TransactionMetadata|MockObject
     */
    private TransactionMetadata|MockObject $transactionMetadata;

    protected function setUp(): void
    {
        $this->commentFormatter = $this->createMock(CommentFormatter::class);
        $this->transactionMetadata = $this->createMock(TransactionMetadata::class);
        $this->applier = new Applier($this->commentFormatter, $this->transactionMetadata);
    }

    public function testApplyAddsAmountsMetadataAndCommentsOnce(): void
    {
        $customCaptureData = new Data(30.0, 24.0, 'Reason');
        $order = $this->createOrderMock();
        $invoice = $this->createInvoiceMock($order);
        $completedMutations = [
            'grand_total' => false,
            'base_grand_total' => false,
            'invoice_comment' => false,
            'order_comment' => false,
        ];
        $invoice->method('getGrandTotal')->willReturn(100.0);
        $invoice->method('getBaseGrandTotal')->willReturn(80.0);
        $invoice->expects($this->once())
            ->method('setGrandTotal')
            ->with(130.0)
            ->willReturnCallback(function () use (&$completedMutations, $invoice) {
                $completedMutations['grand_total'] = true;
                return $invoice;
            });
        $invoice->expects($this->once())
            ->method('setBaseGrandTotal')
            ->with(104.0)
            ->willReturnCallback(function () use (&$completedMutations, $invoice) {
                $completedMutations['base_grand_total'] = true;
                return $invoice;
            });
        $invoice->expects($this->once())
            ->method('addComment')
            ->with('comment', false, false)
            ->willReturnCallback(function () use (&$completedMutations, $invoice) {
                $completedMutations['invoice_comment'] = true;
                return $invoice;
            });
        $order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with('comment', false, false)
            ->willReturnCallback(function () use (&$completedMutations) {
                $completedMutations['order_comment'] = true;
                return null;
            });
        $this->commentFormatter->expects($this->once())
            ->method('format')
            ->with('$30.00', 'Reason')
            ->willReturn('comment');
        $this->transactionMetadata->expects($this->once())
            ->method('stage')
            ->with($invoice, $customCaptureData)
            ->willReturnCallback(function () use (&$completedMutations, $invoice): void {
                $this->assertSame(
                    [
                        'grand_total' => true,
                        'base_grand_total' => true,
                        'invoice_comment' => true,
                        'order_comment' => true,
                    ],
                    $completedMutations
                );
                $this->assertSame(30.0, $invoice->getData(Data::AMOUNT));
                $this->assertSame(24.0, $invoice->getData(Data::BASE_AMOUNT));
                $this->assertSame('Reason', $invoice->getData(Data::REASON));
            });

        $this->applier->apply($invoice, $customCaptureData);
        $this->applier->apply($invoice, $customCaptureData);

        $this->assertSame(30.0, $invoice->getData(Data::AMOUNT));
        $this->assertSame(24.0, $invoice->getData(Data::BASE_AMOUNT));
        $this->assertSame('Reason', $invoice->getData(Data::REASON));
    }

    public function testApplyRejectsConflictingDataWithoutSecondMutation(): void
    {
        $order = $this->createOrderMock();
        $invoice = $this->createInvoiceMock($order);
        $invoice->method('getGrandTotal')->willReturn(100.0);
        $invoice->method('getBaseGrandTotal')->willReturn(80.0);
        $invoice->expects($this->once())->method('setGrandTotal');
        $invoice->expects($this->once())->method('setBaseGrandTotal');
        $invoice->expects($this->once())->method('addComment');
        $order->expects($this->once())->method('addCommentToStatusHistory');
        $this->commentFormatter->expects($this->once())->method('format')->willReturn('comment');
        $this->transactionMetadata->expects($this->once())->method('stage');

        $this->applier->apply($invoice, new Data(30.0, 24.0, 'Reason'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conflicting custom capture data has already been applied to this invoice.');
        $this->applier->apply($invoice, new Data(31.0, 24.8, 'Different reason'));
    }

    public function testApplyTreatsEquivalentRoundedAmountsAsAlreadyApplied(): void
    {
        $order = $this->createOrderMock();
        $invoice = $this->createInvoiceMock($order);
        $invoice->method('getGrandTotal')->willReturn(100.0);
        $invoice->method('getBaseGrandTotal')->willReturn(80.0);
        $invoice->expects($this->once())->method('setGrandTotal');
        $invoice->expects($this->once())->method('setBaseGrandTotal');
        $invoice->expects($this->once())->method('addComment');
        $order->expects($this->once())->method('addCommentToStatusHistory');
        $this->commentFormatter->expects($this->once())->method('format')->willReturn('comment');
        $this->transactionMetadata->expects($this->once())->method('stage');

        $this->applier->apply($invoice, new Data(30.0, 24.0, 'Reason'));
        $this->applier->apply($invoice, new Data(30.000000001, 24.000000001, 'Reason'));
    }

    public function testApplyPropagatesMetadataFailureAfterInvoiceMutations(): void
    {
        $customCaptureData = new Data(30.0, 24.0, 'Reason');
        $order = $this->createOrderMock();
        $invoice = $this->createInvoiceMock($order);
        $invoice->method('getGrandTotal')->willReturn(100.0);
        $invoice->method('getBaseGrandTotal')->willReturn(80.0);
        $invoice->expects($this->once())->method('setGrandTotal')->with(130.0);
        $invoice->expects($this->once())->method('setBaseGrandTotal')->with(104.0);
        $invoice->expects($this->once())->method('addComment')->with('comment', false, false);
        $order->expects($this->once())->method('addCommentToStatusHistory')->with('comment', false, false);
        $this->commentFormatter->expects($this->once())->method('format')->willReturn('comment');
        $this->transactionMetadata->expects($this->once())
            ->method('stage')
            ->willThrowException(new LogicException('Unable to stage metadata.'));

        try {
            $this->applier->apply($invoice, $customCaptureData);
            $this->fail('Expected metadata staging to fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Unable to stage metadata.', $exception->getMessage());
        }

        $this->assertSame(30.0, $invoice->getData(Data::AMOUNT));
        $this->assertSame(24.0, $invoice->getData(Data::BASE_AMOUNT));
        $this->assertSame('Reason', $invoice->getData(Data::REASON));
    }

    public function testApplyDoesNotMutateInvoiceWhenCommentFormattingFails(): void
    {
        $customCaptureData = new Data(30.0, 24.0, 'Reason');
        $order = $this->createOrderMock();
        $invoice = $this->createInvoiceMock($order);
        $invoice->expects($this->never())->method('setGrandTotal');
        $invoice->expects($this->never())->method('setBaseGrandTotal');
        $invoice->expects($this->never())->method('addComment');
        $order->expects($this->never())->method('addCommentToStatusHistory');
        $this->commentFormatter->expects($this->once())
            ->method('format')
            ->willThrowException(new LogicException('Unable to format comment.'));
        $this->transactionMetadata->expects($this->never())->method('stage');

        try {
            $this->applier->apply($invoice, $customCaptureData);
            $this->fail('Expected comment formatting to fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Unable to format comment.', $exception->getMessage());
        }

        $this->assertNull($invoice->getData(Data::AMOUNT));
        $this->assertNull($invoice->getData(Data::BASE_AMOUNT));
        $this->assertNull($invoice->getData(Data::REASON));
    }

    public function testApplyDoesNotStageMetadataWhenInvoiceCommentFails(): void
    {
        $order = $this->createOrderMock();
        $invoice = $this->createInvoiceMock($order);
        $invoice->method('getGrandTotal')->willReturn(100.0);
        $invoice->method('getBaseGrandTotal')->willReturn(80.0);
        $invoice->expects($this->once())->method('setGrandTotal')->with(130.0);
        $invoice->expects($this->once())->method('setBaseGrandTotal')->with(104.0);
        $invoice->expects($this->once())
            ->method('addComment')
            ->willThrowException(new LogicException('Unable to add invoice comment.'));
        $order->expects($this->never())->method('addCommentToStatusHistory');
        $this->commentFormatter->expects($this->once())->method('format')->willReturn('comment');
        $this->transactionMetadata->expects($this->never())->method('stage');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to add invoice comment.');
        $this->applier->apply($invoice, new Data(30.0, 24.0, 'Reason'));
    }

    public function testApplyDoesNotStageMetadataWhenOrderCommentFails(): void
    {
        $order = $this->createOrderMock();
        $invoice = $this->createInvoiceMock($order);
        $invoice->method('getGrandTotal')->willReturn(100.0);
        $invoice->method('getBaseGrandTotal')->willReturn(80.0);
        $invoice->expects($this->once())->method('setGrandTotal')->with(130.0);
        $invoice->expects($this->once())->method('setBaseGrandTotal')->with(104.0);
        $invoice->expects($this->once())->method('addComment')->with('comment', false, false);
        $order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->willThrowException(new LogicException('Unable to add order comment.'));
        $this->commentFormatter->expects($this->once())->method('format')->willReturn('comment');
        $this->transactionMetadata->expects($this->never())->method('stage');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to add order comment.');
        $this->applier->apply($invoice, new Data(30.0, 24.0, 'Reason'));
    }

    public function testApplyRejectsPersistedInvoiceBeforeMutation(): void
    {
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setGrandTotal', 'setBaseGrandTotal', 'addComment'])
            ->getMock();
        $invoice->method('getId')->willReturn(1);
        $invoice->expects($this->never())->method('setGrandTotal');
        $invoice->expects($this->never())->method('setBaseGrandTotal');
        $invoice->expects($this->never())->method('addComment');
        $this->commentFormatter->expects($this->never())->method('format');
        $this->transactionMetadata->expects($this->never())->method('stage');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Custom capture data cannot be applied to a persisted invoice.');
        $this->applier->apply($invoice, new Data(30.0, 24.0, 'Reason'));
    }

    public function testApplyRejectsIncompleteExistingDataBeforeMutation(): void
    {
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setGrandTotal', 'setBaseGrandTotal', 'addComment'])
            ->getMock();
        $invoice->method('getId')->willReturn(null);
        $invoice->setData(Data::REASON, 'Reason');
        $invoice->expects($this->never())->method('setGrandTotal');
        $invoice->expects($this->never())->method('setBaseGrandTotal');
        $invoice->expects($this->never())->method('addComment');
        $this->commentFormatter->expects($this->never())->method('format');
        $this->transactionMetadata->expects($this->never())->method('stage');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Incomplete custom capture data has already been applied to this invoice.');
        $this->applier->apply($invoice, new Data(30.0, 24.0, 'Reason'));
    }

    private function createOrderMock(): Order|MockObject
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addCommentToStatusHistory', 'formatPriceTxt'])
            ->getMock();
        $order->method('formatPriceTxt')
            ->willReturnCallback(
                static fn (float $amount): string => '$' . number_format($amount, 2, '.', '')
            );

        return $order;
    }

    private function createInvoiceMock(Order $order): Invoice|MockObject
    {
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId',
                'getGrandTotal',
                'getBaseGrandTotal',
                'setGrandTotal',
                'setBaseGrandTotal',
                'addComment',
                'getOrder',
            ])
            ->getMock();
        $invoice->method('getId')->willReturn(null);
        $invoice->method('getOrder')->willReturn($order);

        return $invoice;
    }
}

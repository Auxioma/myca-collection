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

namespace Magento\PaymentServicesPaypal\Test\Unit\Controller\Adminhtml\Order\Invoice;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\PaymentServicesPaypal\Controller\Adminhtml\Order\Invoice\UpdateTotals;
use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UpdateTotalsTest extends TestCase
{
    /**
     * @var JsonFactory|MockObject
     */
    private JsonFactory|MockObject $jsonFactory;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $logger;

    public function testReturnsLocalizedValidationError(): void
    {
        $exception = new LocalizedException(__('The order no longer exists.'));
        $controller = $this->createController($exception);
        $jsonResult = $this->createMock(Json::class);

        $this->jsonFactory->expects(self::once())->method('create')->willReturn($jsonResult);
        $jsonResult->expects(self::once())
            ->method('setData')
            ->with(self::callback(
                static fn (array $data): bool => $data['error'] === true
                    && (string) $data['message'] === 'The order no longer exists.'
            ))
            ->willReturnSelf();
        $this->logger->expects(self::never())->method('error');

        self::assertSame($jsonResult, $controller->execute());
    }

    public function testLogsUnexpectedFailureAndReturnsCustomMessage(): void
    {
        $exception = new RuntimeException('Unexpected failure');
        $controller = $this->createController($exception);
        $jsonResult = $this->createMock(Json::class);

        $this->jsonFactory->expects(self::once())->method('create')->willReturn($jsonResult);
        $jsonResult->expects(self::once())
            ->method('setData')
            ->with(self::callback(
                static fn (array $data): bool => $data['error'] === true
                    && (string) $data['message'] === 'Cannot update custom capture totals.'
            ))
            ->willReturnSelf();
        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Cannot update custom capture totals.',
                ['exception' => $exception, 'order_id' => '42']
            );

        self::assertSame($jsonResult, $controller->execute());
    }

    public function testRendersPreparedInvoiceItems(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => match ($name) {
                'order_id' => '42',
                'invoice' => ['items' => [], 'comment_text' => 'Test comment'],
                default => $default,
            }
        );
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('canInvoice')->willReturn(true);
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getTotalQty')->willReturn(1);

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with(42)->willReturn($order);
        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService->expects(self::once())->method('prepareInvoice')->with($order, [])->willReturn($invoice);

        $title = $this->createMock(Title::class);
        $title->expects(self::once())->method('prepend')->with('Invoices');
        $config = $this->createMock(Config::class);
        $config->method('getTitle')->willReturn($title);
        $orderItems = $this->createMock(AbstractBlock::class);
        $orderItems->expects(self::once())->method('toHtml')->willReturn('<div>Invoice items</div>');
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::once())->method('getBlock')->with('order_items')->willReturn($orderItems);
        $resultPage = $this->createMock(Page::class);
        $resultPage->method('getConfig')->willReturn($config);
        $resultPage->method('getLayout')->willReturn($layout);
        $pageFactory = $this->createMock(PageFactory::class);
        $pageFactory->expects(self::once())->method('create')->willReturn($resultPage);

        $rawResult = $this->createMock(Raw::class);
        $rawResult->expects(self::once())->method('setContents')->with('<div>Invoice items</div>')->willReturnSelf();
        $rawFactory = $this->createMock(RawFactory::class);
        $rawFactory->expects(self::once())->method('create')->willReturn($rawResult);
        $invoiceContext = new InvoiceContext();

        $controller = new UpdateTotals(
            $context,
            $invoiceContext,
            $pageFactory,
            $this->createMock(JsonFactory::class),
            $rawFactory,
            $repository,
            $invoiceService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertSame($rawResult, $controller->execute());
        self::assertSame($invoice, $invoiceContext->getInvoice());
    }

    /**
     * @param \Throwable $exception
     * @return UpdateTotals
     */
    private function createController(\Throwable $exception): UpdateTotals
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => match ($name) {
                'order_id' => '42',
                'invoice' => [],
                default => $default,
            }
        );

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->method('get')->with(42)->willThrowException($exception);
        $this->jsonFactory = $this->createMock(JsonFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $controller = new UpdateTotals(
            $context,
            new InvoiceContext(),
            $this->createMock(PageFactory::class),
            $this->jsonFactory,
            $this->createMock(RawFactory::class),
            $repository,
            $this->createMock(InvoiceService::class),
            $this->logger
        );

        return $controller;
    }
}

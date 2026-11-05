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

namespace Magento\PaymentServicesPaypal\Controller\Adminhtml\Order\Invoice;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Magento\PaymentServicesPaypal\Model\CustomCapture\InvoiceContext;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

class UpdateTotals extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Sales::invoice';

    /**
     * @param Context $context
     * @param InvoiceContext $invoiceContext
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param RawFactory $resultRawFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceService $invoiceService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        private readonly InvoiceContext $invoiceContext,
        private readonly PageFactory $resultPageFactory,
        private readonly JsonFactory $resultJsonFactory,
        private readonly RawFactory $resultRawFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceService $invoiceService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Validate custom capture data and return refreshed invoice totals HTML.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $orderId = (int) $this->getRequest()->getParam('order_id');
            $invoiceData = (array) $this->getRequest()->getParam('invoice', []);
            $invoiceItems = $invoiceData['items'] ?? [];

            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
            if (!$order->getId()) {
                throw new LocalizedException(__('The order no longer exists.'));
            }

            if (!$order->canInvoice()) {
                throw new LocalizedException(__('The order does not allow an invoice to be created.'));
            }

            $invoice = $this->invoiceService->prepareInvoice($order, $invoiceItems);
            if (!$invoice->getTotalQty()) {
                throw new LocalizedException(
                    __("The invoice can't be created without products. Add products and try again.")
                );
            }

            $invoice->setCommentText($invoiceData['comment_text'] ?? '');
            $this->invoiceContext->setInvoice($invoice);

            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->prepend(__('Invoices'));
            $response = $resultPage->getLayout()->getBlock('order_items')->toHtml();
        } catch (LocalizedException $exception) {
            $response = ['error' => true, 'message' => $exception->getMessage()];
        } catch (\Exception $exception) {
            $this->logger->error(
                'Cannot update custom capture totals.',
                [
                    'exception' => $exception,
                    'order_id' => $this->getRequest()->getParam('order_id'),
                ]
            );
            $response = ['error' => true, 'message' => __('Cannot update custom capture totals.')];
        }

        if (is_array($response)) {
            $resultJson = $this->resultJsonFactory->create();
            $resultJson->setData($response);
            return $resultJson;
        }

        $resultRaw = $this->resultRawFactory->create();
        $resultRaw->setContents($response);
        return $resultRaw;
    }
}

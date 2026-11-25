<?php

declare(strict_types=1);

namespace Myca\Dropship\Controller\Adminhtml\Packingslip;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Myca\Dropship\Model\ResourceModel\DropshipOrder;
use Myca\Dropship\ViewModel\AdminOrder;

/**
 * Bon de livraison d'une commande en dropshipping : la boutique y figure comme vendeur, sans aucun prix MYCA.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Sales::shipment';

    public function __construct(
        Context $context,
        private readonly RawFactory $rawFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DropshipOrder $dropshipOrder,
        private readonly AdminOrder $adminOrder,
        private readonly LayoutInterface $layout
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $result = $this->rawFactory->create();
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $info = $this->dropshipOrder->getForOrder($orderId);
        try {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception) {
            $order = null;
        }
        if ($order === null || $info === null || $info['mode'] !== 'dropship') {
            return $result->setHttpResponseCode(404)->setContents((string) __('Aucun bon de livraison sans prix pour cette commande.'));
        }
        /** @var Template $block */
        $block = $this->layout->createBlock(Template::class);
        $block->setTemplate('Myca_Dropship::order/packing-slip.phtml')
            ->setData('order', $order)
            ->setData('seller', $this->adminOrder->getSellerContact($info['partner_id']));

        return $result->setHeader('Content-Type', 'text/html; charset=utf-8')->setContents($block->toHtml());
    }
}

<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Production\Controller\Adminhtml\AbstractAction;

class Save extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::orders';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $orderId = (int) $request->getParam('id');

        return $this->onOrder($orderId, fn () => $this->productionManagement->updateOrder(
            $orderId,
            (array) $request->getParam('order', []),
            (array) $request->getParam('qty', []),
            $this->getAuthor()
        ), (string) __('L’ordre est enregistré.'));
    }
}

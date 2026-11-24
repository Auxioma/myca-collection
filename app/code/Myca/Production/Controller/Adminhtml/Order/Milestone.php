<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Production\Controller\Adminhtml\AbstractAction;

class Milestone extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::orders';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $orderId = (int) $request->getParam('id');

        return $this->onOrder($orderId, fn () => $this->productionManagement->setMilestone(
            $orderId,
            (string) $request->getParam('code'),
            (string) $request->getParam('status'),
            (string) $request->getParam('note'),
            $this->getAuthor()
        ), (string) __('Jalon mis à jour.'));
    }
}

<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Production\Controller\Adminhtml\AbstractAction;

class Tolerances extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::quality';

    public function execute(): Redirect
    {
        $orderId = (int) $this->getRequest()->getParam('id');

        return $this->onOrder($orderId, fn () => $this->productionManagement->validateTolerances(
            $orderId,
            (array) $this->getRequest()->getParam('tolerances', []),
            $this->getAuthor()
        ), (string) __('Tolérances et seuils de défauts validés.'));
    }
}

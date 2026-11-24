<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Myca\Production\Controller\Adminhtml\AbstractAction;

/**
 * Fiche d'un ordre de production, ou création d'un ordre prévisionnel.
 */
class Edit extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::orders';

    public function execute(): ResultInterface
    {
        $orderId = (int) $this->getRequest()->getParam('id');
        $order = $orderId > 0 ? $this->productionManagement->getOrder($orderId) : null;
        if ($orderId > 0 && $order === null) {
            $this->messageManager->addErrorMessage(__('Cet ordre de production est introuvable.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        return $this->page('Myca_Production::orders', $order ? (string) __('Ordre %1', $order['increment_id']) : (string) __('Nouvel ordre de production'));
    }
}

<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Production\Controller\Adminhtml\AbstractAction;

class Create extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::orders';

    public function execute(): Redirect
    {
        try {
            $orderId = $this->productionManagement->createOrder((array) $this->getRequest()->getParam('order', []), $this->getAuthor());
            $this->messageManager->addSuccessMessage(__('Ordre prévisionnel créé : renseignez les quantités, le prix usine et les tolérances.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $orderId]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('*/*/edit');
        }
    }
}

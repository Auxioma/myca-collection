<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Supplier;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Production\Controller\Adminhtml\AbstractAction;

class Save extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::suppliers';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $supplierId = (int) $request->getParam('id') ?: null;
        try {
            $supplierId = $this->productionManagement->saveSupplier($supplierId, (array) $request->getParam('supplier', []));
            $this->messageManager->addSuccessMessage(__('Le fournisseur est enregistré.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/edit', $supplierId ? ['id' => $supplierId] : []);
    }
}

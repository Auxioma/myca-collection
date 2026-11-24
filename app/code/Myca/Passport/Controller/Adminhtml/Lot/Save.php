<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml\Lot;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Passport\Controller\Adminhtml\AbstractAction;

/**
 * Création ou correction d'un lot. Toute correction d'un lot existant exige un motif et reste tracée.
 */
class Save extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Passport::manage';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $lotId = (int) $request->getParam('id') ?: null;
        try {
            $lotId = $this->passportManagement->saveLot(
                $lotId,
                (array) $request->getParam('lot', []),
                (array) $request->getParam('status', []),
                (string) $request->getParam('reason'),
                $this->getAuthor()
            );
            $this->messageManager->addSuccessMessage(__('Le lot est enregistré.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/edit', $lotId ? ['id' => $lotId] : []);
    }
}

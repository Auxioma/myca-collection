<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml\Lot;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Passport\Controller\Adminhtml\AbstractAction;

/**
 * Révoque ou remplace un passeport. L'ancien QR reste résolu : page « révoqué », ou renvoi vers le remplaçant.
 */
class Revoke extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Passport::revoke';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $passport = $this->passportManagement->getPassport((int) $request->getParam('passport_id'));
        $lotId = $passport ? (int) $passport['lot_id'] : 0;
        try {
            if ($passport === null) {
                throw new LocalizedException(__('Ce passeport est introuvable.'));
            }
            if ($request->getParam('operation') === 'replace') {
                $this->passportManagement->replace((int) $passport['passport_id'], (string) $request->getParam('reason'), $this->getAuthor());
                $this->messageManager->addSuccessMessage(__('Le passeport est remplacé : imprimez la nouvelle étiquette.'));
            } else {
                $this->passportManagement->revoke((int) $passport['passport_id'], (string) $request->getParam('reason'), $this->getAuthor());
                $this->messageManager->addSuccessMessage(__('Le passeport est révoqué.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath($lotId ? '*/*/edit' : '*/*/', $lotId ? ['id' => $lotId] : []);
    }
}

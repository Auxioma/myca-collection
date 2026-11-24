<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Mes;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Passport\Model\PassportManagement;

/**
 * Enregistre un passeport dans le compte du client (code de l'étiquette, ou bouton de la page du passeport).
 */
class Ajouter implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly PassportManagement $passportManagement,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account/login');
        }
        try {
            $this->passportManagement->claim((int) $this->customerSession->getCustomerId(), (string) $this->request->getParam('code'));
            $this->messageManager->addSuccessMessage(__('Le passeport est enregistré dans « Mes passeports MYCA ».'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('passeport/mes');
    }
}

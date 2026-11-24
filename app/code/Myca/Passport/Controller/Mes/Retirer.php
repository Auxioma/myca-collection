<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Mes;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Myca\Passport\Model\PassportManagement;

/**
 * Retire un passeport de la liste du client (le passeport lui-même n'est pas touché).
 */
class Retirer implements HttpPostActionInterface
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
        if ($this->customerSession->isLoggedIn()) {
            $this->passportManagement->release((int) $this->customerSession->getCustomerId(), (int) $this->request->getParam('id'));
            $this->messageManager->addSuccessMessage(__('Le passeport est retiré de votre liste.'));
        }

        return $this->redirectFactory->create()->setPath('passeport/mes');
    }
}

<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Demande;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Myca\Rma\Model\OrderEligibility;
use Myca\Rma\Model\RequesterAccess;
use Myca\Rma\Model\ReturnPolicy;

/**
 * Formulaire « Demander un retour » d'une commande MYCA en ligne, pour le client connecté ou sans compte
 * (commande retrouvée par « Suivre ma commande »).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly RequesterAccess $access,
        private readonly OrderEligibility $eligibility,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $order = $this->access->getCustomerOrder($this->request, (int) $this->request->getParam('commande'));
        if ($order === null) {
            if (!$this->customerSession->isLoggedIn()) {
                $this->messageManager->addNoticeMessage(__('Connectez-vous, ou retrouvez votre commande avec sa référence, pour demander un retour.'));

                return $this->redirectFactory->create()->setPath('sales/guest/form');
            }
            $this->messageManager->addErrorMessage(__('Cette commande est introuvable.'));

            return $this->redirectFactory->create()->setPath('sales/order/history');
        }
        if ($this->eligibility->getOrigin($order) !== ReturnPolicy::ORIGIN_ONLINE) {
            $this->messageManager->addErrorMessage(__('Cette commande a été passée par une boutique partenaire : la demande se dépose depuis l’espace partenaire.'));

            return $this->redirectFactory->create()->setPath('partenaire/retours');
        }

        $page = $this->pageFactory->create();
        if ($this->customerSession->isLoggedIn()) {
            $page->addHandle('customer_account');
        }
        $page->getConfig()->getTitle()->set((string) __('Demander un retour'));

        return $page;
    }
}

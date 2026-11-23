<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Voir;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Myca\Rma\Model\RequesterAccess;

/**
 * Détail d'une demande pour le client (connecté ou sans compte).
 */
class Index implements HttpGetActionInterface
{
    public const REGISTRY_KEY = 'myca_rma_current';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly RequesterAccess $access,
        private readonly Registry $registry,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $rma = $this->access->getCustomerRma($this->request, (int) $this->request->getParam('id'));
        if ($rma === null) {
            $this->messageManager->addErrorMessage(__('Cette demande est introuvable.'));

            return $this->redirectFactory->create()->setPath($this->customerSession->isLoggedIn() ? 'retours' : 'sales/guest/form');
        }
        $this->registry->register(self::REGISTRY_KEY, $rma);
        $page = $this->pageFactory->create();
        if ($this->customerSession->isLoggedIn()) {
            $page->addHandle('customer_account');
        }
        $page->getConfig()->getTitle()->set((string) __('Demande %1', $rma->getIncrementId()));

        return $page;
    }
}

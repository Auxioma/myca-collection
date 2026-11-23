<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Retours;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Myca\Partner\Controller\AbstractPage;
use Myca\Partner\Model\CurrentPartner;
use Myca\Rma\Model\RequesterAccess;

/**
 * Nouvelle demande de la boutique pour une de ses commandes (dropshipping, ou dossier SAV d'un article en stock).
 */
class Demande extends AbstractPage
{
    protected const TITLE = 'Nouvelle demande de retour';

    public function __construct(
        RequestInterface $request,
        PageFactory $pageFactory,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        CurrentPartner $currentPartner,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly RequesterAccess $access
    ) {
        parent::__construct($request, $pageFactory, $redirectFactory, $customerSession, $currentPartner, $messageManager, $url);
    }

    public function execute(): ResultInterface
    {
        $result = parent::execute();
        if ($result instanceof Page && $this->access->getPartnerOrder((int) $this->request->getParam('commande')) === null) {
            $this->messageManager->addErrorMessage(__('Choisissez une commande de votre boutique.'));

            return $this->redirectFactory->create()->setPath('partenaire/commandes');
        }

        return $result;
    }
}

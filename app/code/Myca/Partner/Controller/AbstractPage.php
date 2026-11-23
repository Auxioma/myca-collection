<?php

declare(strict_types=1);

namespace Myca\Partner\Controller;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Myca\Partner\Model\CurrentPartner;

/**
 * Page de l'espace partenaire : réservée au client connecté rattaché à un partenaire actif.
 */
abstract class AbstractPage implements HttpGetActionInterface
{
    protected const TITLE = 'Espace Partenaire B2B';

    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly PageFactory $pageFactory,
        protected readonly RedirectFactory $redirectFactory,
        protected readonly CustomerSession $customerSession,
        protected readonly CurrentPartner $currentPartner,
        protected readonly ManagerInterface $messageManager,
        protected readonly UrlInterface $url
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl($this->url->getCurrentUrl());

            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        if ($this->currentPartner->getActive() === null) {
            $this->messageManager->addErrorMessage(
                $this->currentPartner->get() === null
                    ? __('Cet espace est réservé aux partenaires MYCA.')
                    : __('Votre compte partenaire n’est pas actif. Contactez le service partenaires.')
            );

            return $this->redirectFactory->create()->setPath('customer/account');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string) __(static::TITLE));

        return $page;
    }
}

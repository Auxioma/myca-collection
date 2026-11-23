<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Index;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * « Mes retours & SAV » du client connecté (achats MYCA en ligne).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly UrlInterface $url
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl($this->url->getCurrentUrl());

            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string) __('Mes retours & SAV'));

        return $page;
    }
}

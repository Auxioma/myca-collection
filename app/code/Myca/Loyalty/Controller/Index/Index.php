<?php

declare(strict_types=1);

namespace Myca\Loyalty\Controller\Index;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Myca\Loyalty\ViewModel\Program;

/**
 * M36 — « Mon programme fidélité ». Absente tant que le programme n'est pas activé, ou pour un compte boutique
 * sans points B2B.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly Program $program,
        private readonly UrlInterface $url
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl($this->url->getCurrentUrl());

            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        if (!$this->program->isAvailable()) {
            return $this->redirectFactory->create()->setPath('customer/account');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string) __('Mon programme fidélité'));

        return $page;
    }
}

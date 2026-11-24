<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\View;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Myca\Passport\Model\PassportManagement;
use Myca\Passport\Model\QrCode;

/**
 * M50 — page publique d'un passeport. Un passeport remplacé renvoie vers le nouveau (ancien QR toujours valable) ;
 * un passeport révoqué affiche son motif. Chaque consultation est comptée de façon anonyme.
 */
class Index implements HttpGetActionInterface
{
    public const REGISTRY_KEY = 'myca_passport_current';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly PassportManagement $passportManagement,
        private readonly RemoteAddress $remoteAddress,
        private readonly Registry $registry,
        private readonly QrCode $qrCode
    ) {
    }

    public function execute(): ResultInterface
    {
        $passport = $this->passportManagement->resolve((string) $this->request->getParam('token'));
        if ($passport === null) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        if ($passport['replaced_by_id'] !== null) {
            $replacement = $this->passportManagement->getPassport((int) $passport['replaced_by_id']);
            if ($replacement !== null) {
                return $this->redirectFactory->create()
                    ->setUrl($this->qrCode->getPublicUrl((string) $replacement['token']) . '?remplace=1')
                    ->setHttpResponseCode(301);
            }
        }
        $this->passportManagement->recordScan((int) $passport['passport_id'], (string) $this->remoteAddress->getRemoteAddress());
        $this->registry->register(self::REGISTRY_KEY, $passport);
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string) __('Passeport du vêtement'));
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');

        return $page;
    }
}

<?php

declare(strict_types=1);

namespace Myca\Partner\Controller;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Myca\Partner\Api\Data\PartnerInterface;
use Myca\Partner\Model\CurrentPartner;

/**
 * Enregistrement depuis l'espace partenaire. La clé de formulaire est vérifiée par Magento (POST de vitrine).
 */
abstract class AbstractSave implements HttpPostActionInterface
{
    protected const BACK_PATH = 'partenaire';

    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly RedirectFactory $redirectFactory,
        protected readonly CustomerSession $customerSession,
        protected readonly CurrentPartner $currentPartner,
        protected readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create()->setPath(static::BACK_PATH);
        $partner = $this->currentPartner->getActive();
        if ($partner === null || !$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account/login');
        }
        $this->save($partner, (int) $this->customerSession->getCustomerId());

        return $redirect;
    }

    abstract protected function save(PartnerInterface $partner, int $customerId): void;
}

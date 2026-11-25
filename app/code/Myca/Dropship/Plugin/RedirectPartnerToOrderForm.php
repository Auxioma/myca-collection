<?php

declare(strict_types=1);

namespace Myca\Dropship\Plugin;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\ResponseInterface;
use Myca\Partner\Model\CurrentPartner;

/**
 * Une boutique ne passe pas par le panier ni le tunnel B2C : sa commande se compose et se valide dans M17
 * (mode de vente, destinataire, tarifs partenaires, moyens de paiement professionnels).
 */
class RedirectPartnerToOrderForm
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CurrentPartner $currentPartner,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function aroundExecute(ActionInterface $subject, callable $proceed): ResultInterface|ResponseInterface
    {
        if ($this->customerSession->isLoggedIn() && $this->currentPartner->getActive() !== null) {
            return $this->redirectFactory->create()->setPath('partenaire/commander');
        }

        return $proceed();
    }
}

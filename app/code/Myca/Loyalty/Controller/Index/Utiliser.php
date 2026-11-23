<?php

declare(strict_types=1);

namespace Myca\Loyalty\Controller\Index;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Loyalty\Model\LoyaltyService;
use Myca\Loyalty\ViewModel\Program;

/**
 * « Utiliser maintenant » : transforme des points en bon d'achat. Clé de formulaire vérifiée par Magento.
 */
class Utiliser implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly LoyaltyService $loyaltyService,
        private readonly Program $program,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->customerSession->isLoggedIn() || !$this->program->isAvailable()) {
            return $redirect->setPath('customer/account/login');
        }
        try {
            $voucher = $this->loyaltyService->redeem((int) $this->customerSession->getCustomerId(), (int) $this->request->getParam('points'));
            $this->messageManager->addSuccessMessage(__(
                'Votre bon d’achat de %1 est prêt : saisissez le code %2 dans votre panier. Il est utilisable une fois, sur votre compte.',
                $this->program->formatPrice($voucher['amount']),
                $voucher['code']
            ));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('fidelite');
    }
}

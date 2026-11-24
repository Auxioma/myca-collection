<?php

declare(strict_types=1);

namespace Myca\Quote\Controller\Action;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Quote\Model\CustomerQuotes;
use Myca\Quote\Model\QuoteManagement;
use Myca\Quote\Model\QuoteStatus;

/**
 * Actions du client sur son devis : envoyer un brouillon, accepter, refuser, annuler, transformer en commande.
 */
class Index implements HttpPostActionInterface
{
    private const ACTIONS = [
        'envoyer' => QuoteStatus::REQUESTED,
        'accepter' => QuoteStatus::ACCEPTED,
        'refuser' => QuoteStatus::REFUSED,
        'annuler' => QuoteStatus::CANCELED,
    ];

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerQuotes $customerQuotes,
        private readonly QuoteManagement $quoteManagement,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        $quote = $this->customerQuotes->get((int) $this->request->getParam('id'));
        if ($quote === null) {
            $this->messageManager->addErrorMessage(__('Ce devis est introuvable.'));

            return $redirect->setPath('devis');
        }
        $action = (string) $this->request->getParam('action');
        try {
            if ($action === 'commander') {
                $this->quoteManagement->convertToCart($quote, $this->checkoutSession->getQuote());
                $this->messageManager->addSuccessMessage(__('Les articles du devis %1 sont dans votre panier, aux prix convenus.', $quote->getIncrementId()));

                return $redirect->setPath('checkout/cart');
            }
            if (!isset(self::ACTIONS[$action])) {
                throw new LocalizedException(__('Action inconnue.'));
            }
            $this->quoteManagement->changeStatus(
                $quote,
                self::ACTIONS[$action],
                QuoteStatus::ACTOR_CUSTOMER,
                $this->customerQuotes->getAuthorName(),
                (string) $this->request->getParam('message')
            );
            $this->messageManager->addSuccessMessage(__('Le devis %1 est à jour.', $quote->getIncrementId()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('devis', ['_query' => ['devis' => $quote->getId()]]);
    }
}

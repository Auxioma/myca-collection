<?php

declare(strict_types=1);

namespace Myca\Quote\Controller\Demande;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Myca\Quote\Model\CustomerQuotes;
use Myca\Quote\Model\QuoteManagement;

/**
 * « Demander un devis » depuis le panier : envoi immédiat ou brouillon. Le panier n'est pas vidé.
 */
class Creer implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerQuotes $customerQuotes,
        private readonly QuoteManagement $quoteManagement,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $url
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl($this->url->getUrl('checkout/cart'));

            return $redirect->setPath('customer/account/login');
        }
        $send = $this->request->getParam('envoyer') !== null;
        try {
            $quote = $this->quoteManagement->createFromCart(
                $this->checkoutSession->getQuote(),
                $this->customerQuotes->getCustomerId(),
                (string) $this->request->getParam('note'),
                $send,
                $this->customerQuotes->getAuthorName()
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('checkout/cart');
        }
        $this->messageManager->addSuccessMessage($send
            ? __('Votre demande de devis %1 est envoyée. MYCA vous répond avec une proposition chiffrée.', $quote->getIncrementId())
            : __('Votre brouillon de devis %1 est enregistré. Vous pourrez l’envoyer depuis « Mes devis ».', $quote->getIncrementId()));

        return $redirect->setPath('devis', ['_query' => ['devis' => $quote->getId()]]);
    }
}

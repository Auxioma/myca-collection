<?php

declare(strict_types=1);

namespace Myca\Quote\Controller\Adminhtml\Quote;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Quote\Model\QuoteManagement;
use Myca\Quote\Model\QuoteStatus;

/**
 * Proposer (prix TTC, validité, conditions) ou refuser une demande de devis.
 */
class Process extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Quote::propose';

    public function __construct(
        Context $context,
        private readonly QuoteManagement $quoteManagement,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $redirect = $this->resultRedirectFactory->create();
        $quote = $this->quoteManagement->load((int) $request->getParam('id'));
        if ($quote === null) {
            $this->messageManager->addErrorMessage(__('Ce devis est introuvable.'));

            return $redirect->setPath('*/*/');
        }
        $user = $this->authSession->getUser();
        $author = $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) : (string) __('MYCA');
        try {
            if ($request->getParam('operation') === 'refuse') {
                $this->quoteManagement->changeStatus($quote, QuoteStatus::REFUSED, QuoteStatus::ACTOR_ADMIN, $author, (string) $request->getParam('note'));
            } else {
                $this->quoteManagement->propose(
                    $quote,
                    (array) $request->getParam('price', []),
                    (string) $request->getParam('valid_until'),
                    (string) $request->getParam('note'),
                    $author
                );
            }
            $this->messageManager->addSuccessMessage(__('Le devis %1 est à jour.', $quote->getIncrementId()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/view', ['id' => $quote->getId()]);
    }
}

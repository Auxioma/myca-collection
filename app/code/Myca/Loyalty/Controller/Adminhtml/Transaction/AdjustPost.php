<?php

declare(strict_types=1);

namespace Myca\Loyalty\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Myca\Loyalty\Model\LoyaltyService;

/**
 * Enregistre un ajustement. Le jeton unique du formulaire empêche qu'un double envoi crédite deux fois.
 */
class AdjustPost extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Loyalty::adjust';

    public function __construct(
        Context $context,
        private readonly LoyaltyService $loyaltyService,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $redirect = $this->resultRedirectFactory->create();
        $user = $this->authSession->getUser();
        $author = $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) : (string) __('Administration');
        $token = (string) $request->getParam('token');
        try {
            if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
                throw new LocalizedException(__('Formulaire expiré : rechargez la page.'));
            }
            $customer = $this->customerRepository->get((string) $request->getParam('email'));
            $recorded = $this->loyaltyService->adjust(
                (int) $customer->getId(),
                (int) $request->getParam('points'),
                (string) $request->getParam('reason'),
                $author,
                $token
            );
            $recorded
                ? $this->messageManager->addSuccessMessage(__('Le solde de %1 est ajusté.', $customer->getEmail()))
                : $this->messageManager->addNoticeMessage(__('Cet ajustement était déjà enregistré.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('Aucun client avec cet e-mail.'));

            return $redirect->setPath('*/*/adjust');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/adjust');
        }

        return $redirect->setPath('*/*/');
    }
}

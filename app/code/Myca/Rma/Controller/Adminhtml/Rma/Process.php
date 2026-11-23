<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;
use Myca\Rma\Model\RmaFactory;
use Myca\Rma\Model\RmaManagement;
use Myca\Rma\Model\StatusFlow;

/**
 * Traitement par le service client : changement d'état, contrôle à réception, remboursement / avoir, message.
 * Le remboursement exige en plus le droit Myca_Rma::refund.
 */
class Process extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Rma::manage';

    public function __construct(
        Context $context,
        private readonly RmaFactory $rmaFactory,
        private readonly RmaResource $rmaResource,
        private readonly RmaManagement $rmaManagement,
        private readonly AuthSession $authSession
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $rma = $this->rmaFactory->create();
        $this->rmaResource->load($rma, (int) $request->getParam('id'));
        $redirect = $this->resultRedirectFactory->create();
        if (!$rma->getId()) {
            $this->messageManager->addErrorMessage(__('Cette demande est introuvable.'));

            return $redirect->setPath('*/*/');
        }
        $user = $this->authSession->getUser();
        $author = $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) : (string) __('Service client');
        $message = (string) $request->getParam('message');
        try {
            switch ((string) $request->getParam('operation')) {
                case 'status':
                    $this->rmaManagement->changeStatus(
                        $rma,
                        (string) $request->getParam('status'),
                        StatusFlow::ACTOR_ADMIN,
                        RmaManagement::AUTHOR_ADMIN,
                        $author,
                        $message
                    );
                    break;
                case 'inspect':
                    $this->rmaManagement->inspect($rma, (array) $request->getParam('condition', []), $author, $message);
                    break;
                case 'refund':
                    if (!$this->_authorization->isAllowed('Myca_Rma::refund')) {
                        throw new LocalizedException(__('Vous n’avez pas le droit d’émettre un remboursement.'));
                    }
                    $this->rmaManagement->refund($rma, $author);
                    break;
                case 'message':
                    $this->rmaManagement->addMessage($rma, RmaManagement::AUTHOR_ADMIN, $author, $message, (bool) $request->getParam('visible'));
                    break;
                default:
                    throw new LocalizedException(__('Action inconnue.'));
            }
            $this->messageManager->addSuccessMessage(__('La demande %1 est à jour.', $rma->getIncrementId()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/view', ['id' => $rma->getId()]);
    }
}

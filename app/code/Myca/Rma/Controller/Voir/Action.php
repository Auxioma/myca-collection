<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Voir;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Rma\Model\RequesterAccess;
use Myca\Rma\Model\RmaManagement;
use Myca\Rma\Model\StatusFlow;

/**
 * Actions du client sur sa demande : annuler, signaler l'envoi du colis, écrire au service client.
 */
class Action implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly RequesterAccess $access,
        private readonly RmaManagement $rmaManagement,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $rma = $this->access->getCustomerRma($this->request, (int) $this->request->getParam('id'));
        if ($rma === null) {
            $this->messageManager->addErrorMessage(__('Cette demande est introuvable.'));

            return $this->redirectFactory->create()->setPath('retours');
        }
        $author = $this->access->getAuthorName();
        try {
            match ((string) $this->request->getParam('action')) {
                'annuler' => $this->rmaManagement->changeStatus($rma, StatusFlow::CANCELED, StatusFlow::ACTOR_REQUESTER, RmaManagement::AUTHOR_CUSTOMER, $author),
                'expedie' => $this->rmaManagement->changeStatus($rma, StatusFlow::IN_TRANSIT, StatusFlow::ACTOR_REQUESTER, RmaManagement::AUTHOR_CUSTOMER, $author),
                'message' => $this->rmaManagement->addMessage($rma, RmaManagement::AUTHOR_CUSTOMER, $author, (string) $this->request->getParam('message')),
                default => throw new LocalizedException(__('Action inconnue.')),
            };
            $this->messageManager->addSuccessMessage(__('Votre demande %1 est à jour.', $rma->getIncrementId()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->redirectFactory->create()->setPath('retours/voir', ['_query' => ['id' => $rma->getId()]]);
    }
}

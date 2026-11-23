<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Retours;

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
 * Actions de la boutique sur ses demandes (M23) : annuler, signaler l'envoi, déclarer le remboursement de son
 * client (dropshipping), écrire au SAV MYCA.
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
        $rma = $this->access->getPartnerRma((int) $this->request->getParam('id'));
        if ($rma === null) {
            $this->messageManager->addErrorMessage(__('Cette demande est introuvable.'));

            return $this->redirectFactory->create()->setPath('partenaire/retours');
        }
        $author = $this->access->getAuthorName();
        try {
            match ((string) $this->request->getParam('action')) {
                'annuler' => $this->rmaManagement->changeStatus($rma, StatusFlow::CANCELED, StatusFlow::ACTOR_REQUESTER, RmaManagement::AUTHOR_PARTNER, $author),
                'expedie' => $this->rmaManagement->changeStatus($rma, StatusFlow::IN_TRANSIT, StatusFlow::ACTOR_REQUESTER, RmaManagement::AUTHOR_PARTNER, $author),
                'client-rembourse' => $this->rmaManagement->declarePartnerRefund($rma, $author),
                'message' => $this->rmaManagement->addMessage($rma, RmaManagement::AUTHOR_PARTNER, $author, (string) $this->request->getParam('message')),
                default => throw new LocalizedException(__('Action inconnue.')),
            };
            $this->messageManager->addSuccessMessage(__('La demande %1 est à jour.', $rma->getIncrementId()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->redirectFactory->create()->setPath('partenaire/retours', ['_query' => ['demande' => $rma->getId()]]);
    }
}

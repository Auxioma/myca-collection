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

/**
 * Enregistre la demande d'une boutique, pour une commande de cette boutique uniquement.
 */
class Enregistrer implements HttpPostActionInterface
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
        $orderId = (int) $this->request->getParam('commande');
        $order = $this->access->getPartnerOrder($orderId);
        if ($order === null) {
            $this->messageManager->addErrorMessage(__('Cette commande est introuvable.'));

            return $this->redirectFactory->create()->setPath('partenaire/retours');
        }
        try {
            $rma = $this->rmaManagement->create(
                $order,
                (string) $this->request->getParam('type'),
                (string) $this->request->getParam('reason'),
                (array) $this->request->getParam('qty', []),
                (array) $this->request->getParam('wish', []),
                (string) $this->request->getParam('comment'),
                RmaManagement::AUTHOR_PARTNER,
                $this->access->getAuthorName(),
                $this->access->getCustomerId(),
                $this->request->getParam('origin') === null ? null : (string) $this->request->getParam('origin')
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectFactory->create()->setPath('partenaire/retours/demande', ['_query' => ['commande' => $orderId]]);
        }
        $this->messageManager->addSuccessMessage(__('La demande %1 est enregistrée.', $rma->getIncrementId()));

        return $this->redirectFactory->create()->setPath('partenaire/retours', ['_query' => ['demande' => $rma->getId()]]);
    }
}

<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Demande;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Rma\Model\RequesterAccess;
use Myca\Rma\Model\RmaManagement;

/**
 * Enregistre la demande du client. La clé de formulaire est vérifiée par Magento (POST de vitrine).
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
        $order = $this->access->getCustomerOrder($this->request, $orderId);
        if ($order === null) {
            $this->messageManager->addErrorMessage(__('Cette commande est introuvable.'));

            return $this->redirectFactory->create()->setPath('sales/guest/form');
        }
        try {
            $rma = $this->rmaManagement->create(
                $order,
                (string) $this->request->getParam('type'),
                (string) $this->request->getParam('reason'),
                (array) $this->request->getParam('qty', []),
                (array) $this->request->getParam('wish', []),
                (string) $this->request->getParam('comment'),
                RmaManagement::AUTHOR_CUSTOMER,
                $this->access->getAuthorName($order),
                $this->access->getCustomerId()
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectFactory->create()->setPath('retours/demande', ['_query' => ['commande' => $orderId]]);
        }
        $this->messageManager->addSuccessMessage(__(
            'Votre demande %1 est enregistrée. Nous l’examinons et vous envoyons les instructions de retour.',
            $rma->getIncrementId()
        ));

        return $this->redirectFactory->create()->setPath('retours/voir', ['_query' => ['id' => $rma->getId()]]);
    }
}

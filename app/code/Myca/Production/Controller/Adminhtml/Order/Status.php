<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Production\Controller\Adminhtml\AbstractAction;

/**
 * Engager, lancer la fabrication, clore la réception ou annuler un ordre.
 */
class Status extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::commit';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $orderId = (int) $request->getParam('id');

        return $this->onOrder($orderId, fn () => $this->productionManagement->changeStatus(
            $orderId,
            (string) $request->getParam('status'),
            $this->getAuthor(),
            (string) $request->getParam('message')
        ), (string) __('Statut mis à jour.'));
    }
}

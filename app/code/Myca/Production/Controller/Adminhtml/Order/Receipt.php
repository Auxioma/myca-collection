<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Production\Controller\Adminhtml\AbstractAction;

/**
 * Réception et contrôle qualité d'un lot, photos comprises.
 */
class Receipt extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::quality';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $orderId = (int) $request->getParam('id');

        return $this->onOrder($orderId, function () use ($request, $orderId): void {
            $receiptId = $this->productionManagement->recordReceipt(
                $orderId,
                (array) $request->getParam('receipt', []),
                (array) $request->getParam('qty', []),
                $this->getAuthor()
            );
            $files = $request instanceof HttpRequest ? (array) $request->getFiles('photos') : [];
            $ids = [];
            foreach (array_keys($files) as $index) {
                if (!empty($files[$index]['name'])) {
                    $ids[] = 'photos[' . $index . ']';
                }
            }
            $this->productionManagement->attachPhotos($receiptId, $ids);
        }, (string) __('Réception enregistrée.'));
    }
}

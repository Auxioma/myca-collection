<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Production\Controller\Adminhtml\AbstractAction;

class Quarantine extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::quality';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $receipt = $this->productionManagement->getReceipt((int) $request->getParam('receipt_id'));

        return $this->onOrder((int) ($receipt['order_id'] ?? 0), fn () => $this->productionManagement->releaseQuarantine(
            (int) $request->getParam('receipt_id'),
            (string) $request->getParam('decision'),
            (string) $request->getParam('note'),
            $this->getAuthor()
        ), (string) __('Quarantaine levée.'));
    }
}

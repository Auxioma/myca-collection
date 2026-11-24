<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Partner;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

class Status extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::status';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $partnerId = (int) $request->getParam('id');

        return $this->run(
            fn () => $this->partnerManagement->setStatus($partnerId, (string) $request->getParam('status'), (string) $request->getParam('reason')),
            (string) __('Le statut de la boutique est mis à jour.'),
            '*/partner/edit',
            ['id' => $partnerId]
        );
    }
}

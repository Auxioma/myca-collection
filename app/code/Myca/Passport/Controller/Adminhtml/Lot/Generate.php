<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml\Lot;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Myca\Passport\Controller\Adminhtml\AbstractAction;

/**
 * Génère les passeports du lot : un par variante, ou N passeports numérotés pour une variante.
 */
class Generate extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Passport::manage';

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $lotId = (int) $request->getParam('id');
        try {
            $count = $request->getParam('mode') === 'serial'
                ? $this->passportManagement->generateSerials($lotId, (string) $request->getParam('sku'), (int) $request->getParam('count'), $this->getAuthor())
                : $this->passportManagement->generateForVariants($lotId, $this->getAuthor());
            $this->messageManager->addSuccessMessage(__('%1 passeport(s) créé(s).', $count));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $lotId]);
    }
}

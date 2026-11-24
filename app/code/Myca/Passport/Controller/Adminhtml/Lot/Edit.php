<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml\Lot;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Myca\Passport\Controller\Adminhtml\AbstractAction;

/**
 * Fiche d'un lot (ou nouveau lot) : données et statuts, passeports, historique.
 */
class Edit extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Passport::passport';

    public function execute(): ResultInterface
    {
        $lotId = (int) $this->getRequest()->getParam('id');
        $lot = $lotId > 0 ? $this->passportManagement->getLot($lotId) : null;
        if ($lotId > 0 && $lot === null) {
            $this->messageManager->addErrorMessage(__('Ce lot est introuvable.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Passport::passport');
        $page->getConfig()->getTitle()->prepend($lot ? (string) __('Lot %1', $lot['lot_code']) : (string) __('Nouveau lot'));

        return $page;
    }
}

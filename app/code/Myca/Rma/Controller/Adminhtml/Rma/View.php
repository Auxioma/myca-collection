<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Backend\Model\View\Result\Page;
use Myca\Rma\Model\ResourceModel\Rma as RmaResource;
use Myca\Rma\Model\RmaFactory;

/**
 * Fiche de traitement d'une demande.
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Rma::rma';
    public const REGISTRY_KEY = 'myca_rma_admin_current';

    public function __construct(
        Context $context,
        private readonly RmaFactory $rmaFactory,
        private readonly RmaResource $rmaResource,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $rma = $this->rmaFactory->create();
        $this->rmaResource->load($rma, (int) $this->getRequest()->getParam('id'));
        if (!$rma->getId()) {
            $this->messageManager->addErrorMessage(__('Cette demande est introuvable.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $this->registry->register(self::REGISTRY_KEY, $rma);
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Rma::rma');
        $page->getConfig()->getTitle()->prepend((string) __('Demande %1', $rma->getIncrementId()));

        return $page;
    }
}

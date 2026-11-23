<?php

declare(strict_types=1);

namespace Myca\Rma\Controller\Adminhtml\Rma;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\Model\View\Result\Page;

/**
 * Ventes › Retours & SAV : grille de toutes les demandes (clients et boutiques).
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Rma::rma';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Rma::rma');
        $page->getConfig()->getTitle()->prepend((string) __('Retours & SAV'));

        return $page;
    }
}

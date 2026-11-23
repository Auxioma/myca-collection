<?php

declare(strict_types=1);

namespace Myca\Loyalty\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Clients › Programme fidélité : journal de tous les mouvements de points.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Loyalty::loyalty';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Loyalty::loyalty');
        $page->getConfig()->getTitle()->prepend((string) __('Programme fidélité'));

        return $page;
    }
}

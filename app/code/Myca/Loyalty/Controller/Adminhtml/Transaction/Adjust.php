<?php

declare(strict_types=1);

namespace Myca\Loyalty\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Formulaire d'ajustement manuel d'un solde (geste commercial, correction).
 */
class Adjust extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Loyalty::adjust';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Loyalty::loyalty');
        $page->getConfig()->getTitle()->prepend((string) __('Ajuster un solde de points'));

        return $page;
    }
}

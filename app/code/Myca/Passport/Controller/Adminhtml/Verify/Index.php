<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml\Verify;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Vérification d'un passeport par le support : statut, lot, consultations, sans aucune donnée personnelle.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Passport::verify';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Passport::verify');
        $page->getConfig()->getTitle()->prepend((string) __('Vérifier un passeport'));

        return $page;
    }
}

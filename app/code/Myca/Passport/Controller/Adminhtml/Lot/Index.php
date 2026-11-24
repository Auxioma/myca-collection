<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml\Lot;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Catalogue › Inventaire › Passeports du vêtement : lots de fabrication.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Passport::passport';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Passport::passport');
        $page->getConfig()->getTitle()->prepend((string) __('Passeports du vêtement'));

        return $page;
    }
}

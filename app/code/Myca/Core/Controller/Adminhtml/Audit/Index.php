<?php

declare(strict_types=1);

namespace Myca\Core\Controller\Adminhtml\Audit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\PageFactory;

/**
 * Journal d'audit (consultation seule).
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Core::audit';

    public function __construct(Context $context, private readonly PageFactory $pageFactory)
    {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Myca_Core::audit');
        $page->getConfig()->getTitle()->prepend((string) __('Journal d’audit MYCA'));

        return $page;
    }
}

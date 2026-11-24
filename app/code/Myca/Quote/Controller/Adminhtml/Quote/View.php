<?php

declare(strict_types=1);

namespace Myca\Quote\Controller\Adminhtml\Quote;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Myca\Quote\Model\QuoteManagement;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Quote::quote';
    public const REGISTRY_KEY = 'myca_quote_admin_current';

    public function __construct(
        Context $context,
        private readonly QuoteManagement $quoteManagement,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $quote = $this->quoteManagement->load((int) $this->getRequest()->getParam('id'));
        if ($quote === null) {
            $this->messageManager->addErrorMessage(__('Ce devis est introuvable.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $this->registry->register(self::REGISTRY_KEY, $quote);
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Myca_Quote::quote');
        $page->getConfig()->getTitle()->prepend((string) __('Devis %1', $quote->getIncrementId()));

        return $page;
    }
}

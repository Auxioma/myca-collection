<?php

declare(strict_types=1);

namespace Myca\Quote\Controller\Adminhtml\Quote;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Myca\Quote\Model\QuoteManagement;

class Pdf extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Quote::quote';

    public function __construct(
        Context $context,
        private readonly QuoteManagement $quoteManagement,
        private readonly RawFactory $rawFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $quote = $this->quoteManagement->load((int) $this->getRequest()->getParam('id'));
        try {
            if ($quote === null) {
                throw new LocalizedException(__('Ce devis est introuvable.'));
            }
            $content = $this->quoteManagement->getPdf($quote);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/pdf', true)
            ->setHeader('Content-Disposition', sprintf('attachment; filename="%s-v%d.pdf"', $quote->getIncrementId(), (int) $quote->getData('version')), true)
            ->setContents($content);

        return $result;
    }
}

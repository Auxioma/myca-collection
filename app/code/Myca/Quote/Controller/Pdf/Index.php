<?php

declare(strict_types=1);

namespace Myca\Quote\Controller\Pdf;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Myca\Quote\Model\CustomerQuotes;
use Myca\Quote\Model\QuoteManagement;

/**
 * Téléchargement du PDF figé de la dernière proposition, pour le titulaire du devis.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerQuotes $customerQuotes,
        private readonly QuoteManagement $quoteManagement,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $quote = $this->customerQuotes->get((int) $this->request->getParam('id'));
        try {
            if ($quote === null) {
                throw new LocalizedException(__('Ce devis est introuvable.'));
            }
            $content = $this->quoteManagement->getPdf($quote);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectFactory->create()->setPath('devis');
        }
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/pdf', true)
            ->setHeader('Content-Disposition', sprintf('attachment; filename="%s-v%d.pdf"', $quote->getIncrementId(), (int) $quote->getData('version')), true)
            ->setHeader('Cache-Control', 'private, no-store', true)
            ->setContents($content);

        return $result;
    }
}

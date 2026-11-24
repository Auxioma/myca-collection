<?php

declare(strict_types=1);

namespace Myca\Passport\Controller\Adminhtml\Lot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Myca\Passport\Model\PassportManagement;
use Myca\Passport\Model\QrCode;

/**
 * QR code d'un passeport au format SVG, pour l'impression des étiquettes.
 */
class Qr extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Passport::passport';

    public function __construct(
        Context $context,
        private readonly PassportManagement $passportManagement,
        private readonly QrCode $qrCode,
        private readonly RawFactory $rawFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $passport = $this->passportManagement->getPassport((int) $this->getRequest()->getParam('passport_id'));
        if ($passport === null) {
            $this->messageManager->addErrorMessage(__('Ce passeport est introuvable.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $name = (string) ($passport['serial_number'] ?: $passport['sku']);
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'image/svg+xml', true)
            ->setHeader('Content-Disposition', sprintf('attachment; filename="passeport-%s.svg"', preg_replace('/[^A-Za-z0-9-]/', '_', $name)), true)
            ->setContents($this->qrCode->getSvg((string) $passport['token'], true));

        return $result;
    }
}

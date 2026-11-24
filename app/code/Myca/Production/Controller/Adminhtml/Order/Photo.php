<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Myca\Production\Model\ProductionManagement;

/**
 * Photo d'un contrôle qualité (fichiers hors du dossier public, accès administrateur seulement).
 */
class Photo extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::orders';

    public function __construct(
        Context $context,
        private readonly ProductionManagement $productionManagement,
        private readonly Filesystem $filesystem,
        private readonly RawFactory $rawFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $receiptId = (int) $this->getRequest()->getParam('receipt_id');
        $file = (string) $this->getRequest()->getParam('file');
        if (!in_array($file, $this->productionManagement->getPhotos($receiptId), true)) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $path = ProductionManagement::PHOTO_DIR . '/' . $receiptId . '/' . $file;
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream', true)
            ->setHeader('X-Content-Type-Options', 'nosniff', true)
            ->setContents($directory->readFile($path));

        return $result;
    }
}

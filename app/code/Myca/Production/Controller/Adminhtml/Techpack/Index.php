<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Techpack;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Myca\Production\Controller\Adminhtml\AbstractAction;

/**
 * M11 — Fiches techniques des modèles.
 */
class Index extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::techpacks';

    public function execute(): Page
    {
        return $this->page('Myca_Production::techpacks', (string) __('Fiches techniques'));
    }
}

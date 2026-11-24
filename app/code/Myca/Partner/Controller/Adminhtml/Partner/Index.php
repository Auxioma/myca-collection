<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Partner;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

/**
 * M01 — Partenaires (B2B) : grille des boutiques.
 */
class Index extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::partners';

    public function execute(): Page
    {
        return $this->page('Myca_Partner::partners', (string) __('Partenaires B2B'));
    }
}

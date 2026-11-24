<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Partner;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

/**
 * Fiche d'une boutique partenaire, ou création d'une boutique et de son titulaire.
 */
class Edit extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::partners';

    public function execute(): Page
    {
        return $this->page('Myca_Partner::partners', (int) $this->getRequest()->getParam('id') ? (string) __('Fiche partenaire') : (string) __('Nouveau partenaire'));
    }
}

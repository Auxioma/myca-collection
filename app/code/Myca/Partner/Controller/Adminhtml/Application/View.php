<?php

declare(strict_types=1);

namespace Myca\Partner\Controller\Adminhtml\Application;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Myca\Partner\Controller\Adminhtml\AbstractAction;

class View extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Partner::applications';

    public function execute(): Page
    {
        return $this->page('Myca_Partner::applications', (string) __('Candidature partenaire'));
    }
}

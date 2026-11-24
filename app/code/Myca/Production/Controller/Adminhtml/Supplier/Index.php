<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Supplier;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Myca\Production\Controller\Adminhtml\AbstractAction;

class Index extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::suppliers';

    public function execute(): Page
    {
        return $this->page('Myca_Production::suppliers', (string) __('Fournisseurs et usines'));
    }
}

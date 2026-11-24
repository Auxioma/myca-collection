<?php

declare(strict_types=1);

namespace Myca\Production\Controller\Adminhtml\Order;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Myca\Production\Controller\Adminhtml\AbstractAction;

/**
 * M10 — Gestion de la production : indicateurs et grille des ordres.
 */
class Index extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Myca_Production::orders';

    public function execute(): Page
    {
        return $this->page('Myca_Production::orders', (string) __('Gestion de la production'));
    }
}

<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Duo;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;

/**
 * Duo Security configuration controller
 */
class Configure extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_TwoFactorAuth::tfa';

    /**
     * @inheritdoc
     */
    public function execute()
    {
        return $this->_redirect('*/*/auth');
    }
}

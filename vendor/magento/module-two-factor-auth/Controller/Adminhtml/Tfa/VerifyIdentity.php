<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Tfa;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Helper\Data as Helper;

/**
 * User identity verification page
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class VerifyIdentity extends AbstractAction implements HttpGetActionInterface
{
    /*
     * string
     */
    private const TFA_EMAIL_SENT = 'verify_email_sent';

    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_TwoFactorAuth::tfa';

    /**
     * @param Context $context
     * @param Session $session
     * @param TfaProviderSessionInterface $tfaProviderSession
     * @param Helper $helper
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly TfaProviderSessionInterface $tfaProviderSession,
        private readonly Helper $helper
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            $user = $this->session->getUser();
            if (!$this->session->getData(self::TFA_EMAIL_SENT)) {
                $this->helper->sendIdentityVerificationLink($user);
                $this->session->setData(self::TFA_EMAIL_SENT, true);
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage('Failed to send the message. Please contact the administrator');
        }
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed()
    {
        $user = $this->session->getUser();

        return
            $user &&
            !$this->tfaProviderSession->isNewProviderConfigurationAllowed() &&
            $this->tfaProviderSession->getProviderToConfigure();
    }
}

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
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;

/**
 * User identity verification page
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class VerifyIdentityRequest extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_TwoFactorAuth::tfa';

    /**
     * @param Context $context
     * @param Session $session
     * @param TfaProviderSessionInterface $tfaProviderSession
     * @param HtmlAreaTokenVerifier $htmlAreaTokenVerifier
     * @param TfaInterface $tfa
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly TfaProviderSessionInterface $tfaProviderSession,
        private readonly HtmlAreaTokenVerifier $htmlAreaTokenVerifier,
        private readonly TfaInterface $tfa
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            if ($this->htmlAreaTokenVerifier->isConfigTokenProvided()) {
                $this->tfaProviderSession->setNewProviderConfigurationAllowed($this->tfaProviderSession::ALLOW);
                return $this->_redirect(
                    $this->tfa->getProviderByCode(
                        $this->tfaProviderSession->getProviderToConfigure()
                    )->getConfigureAction()
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage('Identity verification failed. Please try again later.');
        }

        return $this->_redirect("tfa/tfa/index");
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

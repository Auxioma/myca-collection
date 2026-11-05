<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\U2f;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Api\UserConfigManagerInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\U2fKey;
use Magento\User\Model\User;

/**
 * UbiKey authenticator verification page
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class VerifyIdentity extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param UserConfigManagerInterface $userConfigManager
     * @param TfaInterface $tfa
     * @param TfaProviderSessionInterface $tfaProviderSession
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly PageFactory $pageFactory,
        private readonly UserConfigManagerInterface $userConfigManager,
        private readonly TfaInterface $tfa,
        private readonly TfaProviderSessionInterface $tfaProviderSession
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $this->userConfigManager->setDefaultProvider((int) $this->session->getUser()->getId(), U2fKey::CODE);
        return $this->pageFactory->create();
    }

    /**
     * @inheritDoc
     */
    protected function _isAllowed()
    {
        $user = $this->session->getUser();

        return $user && $this->isUserAllowed($user);
    }

    /**
     * Check user has access or not.
     *
     * @param User $user
     * @return bool
     */
    private function isUserAllowed(User $user): bool
    {
        return
            !$this->tfaProviderSession->isNewProviderConfigurationAllowed() &&
            $this->tfaProviderSession->getProviderToConfigure() &&
            $this->tfa->getProviderIsAllowed((int) $user->getId(), U2fKey::CODE) &&
            $this->tfa->getProvider(U2fKey::CODE)->isActive((int) $user->getId());
    }
}

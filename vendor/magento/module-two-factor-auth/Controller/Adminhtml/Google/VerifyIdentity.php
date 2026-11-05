<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Google;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\Google;
use Magento\User\Model\User;

/**
 * Google authenticator verification page
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class VerifyIdentity extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param TfaInterface $tfa
     * @param TfaProviderSessionInterface $tfaProviderSession
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly PageFactory $pageFactory,
        private readonly TfaInterface $tfa,
        private readonly TfaProviderSessionInterface $tfaProviderSession
    ) {
        parent::__construct($context);
    }

    /**
     * Get current user
     *
     * @return User|null
     */
    private function getUser(): ?User
    {
        return $this->session->getUser();
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        return $this->pageFactory->create();
    }

    /**
     * @inheritDoc
     */
    protected function _isAllowed()
    {
        $user = $this->getUser();

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
            $this->tfa->getProviderIsAllowed((int) $user->getId(), Google::CODE) &&
            $this->tfa->getProvider(Google::CODE)->isActive((int) $user->getId());
    }
}

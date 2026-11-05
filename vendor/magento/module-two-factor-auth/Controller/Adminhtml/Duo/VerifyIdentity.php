<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Duo;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Api\UserConfigManagerInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\DuoSecurity;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;
use Magento\User\Model\User;

/**
 * Duo security identity verification page
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class VerifyIdentity extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @param Context $context
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param UserConfigManagerInterface $userConfigManager
     * @param TfaInterface $tfa
     * @param HtmlAreaTokenVerifier $tokenVerifier
     * @param DuoSecurity $duoSecurity
     * @param TfaProviderSessionInterface $tfaProviderSession
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly PageFactory $pageFactory,
        private readonly UserConfigManagerInterface $userConfigManager,
        private readonly TfaInterface $tfa,
        private readonly HtmlAreaTokenVerifier $tokenVerifier,
        private readonly DuoSecurity $duoSecurity,
        private readonly TfaProviderSessionInterface $tfaProviderSession
    ) {
        parent::__construct($context);
        $this->messageManager = $context->getMessageManager();
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
    }

    /**
     * Get current user
     *
     * @return User|null
     */
    private function getUser()
    {
        return $this->session->getUser();
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $user = $this->getUser();
        if (!$user) {
            $this->messageManager->addErrorMessage(__('User session not found.'));
        }
        $this->userConfigManager->setDefaultProvider((int)$this->getUser()->getId(), DuoSecurity::CODE);

        $this->duoSecurity->setUserIdentityCallbackUrl('tfa/duo/verifyidentitypost');
        $username = $this->getUser()->getUserName();
        $state = $this->duoSecurity->generateDuoState();
        $this->session->setDuoState($state);
        $response = $this->duoSecurity->initiateAuth($username, $state);
        if ($response['status'] === 'failure') {
            // if health check fails, skip the Duo prompt and choose different 2FA.
            $this->messageManager->addErrorMessage($response['message']);
        }

        $resultPage = $this->pageFactory->create();
        $block = $resultPage->getLayout()->getBlock('content');

        if ($block) {
            $block->setData('auth_url', $response['redirect_url']);
        }

        return $resultPage;
    }

    /**
     * Check if admin has permissions to visit related pages
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function _isAllowed()
    {
        if (!parent::_isAllowed()) {
            return false;
        }

        // 1st time users must have the token.
        $user = $this->getUser();

        return $user && $this->isUserAllowed($user);
    }

    /**
     * Check user has access or not.
     *
     * @param User $user
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isUserAllowed(User $user): bool
    {
        return
            !$this->tfaProviderSession->isNewProviderConfigurationAllowed() &&
            $this->tfaProviderSession->getProviderToConfigure() &&
            $this->tfa->getProviderIsAllowed((int)$user->getId(), DuoSecurity::CODE)
            && (
                $this->userConfigManager->isProviderConfigurationActive((int)$user->getId(), DuoSecurity::CODE)
                || $this->tokenVerifier->isConfigTokenProvided()
            );
    }
}

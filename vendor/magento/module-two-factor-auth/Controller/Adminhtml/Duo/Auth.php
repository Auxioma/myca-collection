<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Duo;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Api\UserConfigManagerInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\DuoSecurity;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;

/**
 * Duo security authentication page
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Auth extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @var TfaInterface
     */
    private TfaInterface $tfa;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @var PageFactory
     */
    private PageFactory $pageFactory;

    /**
     * @var UserConfigManagerInterface
     */
    private UserConfigManagerInterface $userConfigManager;

    /**
     * @var HtmlAreaTokenVerifier
     */
    private HtmlAreaTokenVerifier $tokenVerifier;

    /**
     * @var DuoSecurity
     */
    private DuoSecurity $duoSecurity;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var TfaProviderSessionInterface
     */
    private TfaProviderSessionInterface $tfaProviderSession;

    /**
     * @param Context $context
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param UserConfigManagerInterface $userConfigManager
     * @param TfaInterface $tfa
     * @param HtmlAreaTokenVerifier $tokenVerifier
     * @param DuoSecurity $duoSecurity
     * @param TfaProviderSessionInterface|null $tfaProviderSession
     */
    public function __construct(
        Action\Context $context,
        Session $session,
        PageFactory $pageFactory,
        UserConfigManagerInterface $userConfigManager,
        TfaInterface $tfa,
        HtmlAreaTokenVerifier $tokenVerifier,
        DuoSecurity $duoSecurity,
        ?TfaProviderSessionInterface $tfaProviderSession = null
    ) {
        parent::__construct($context);
        $this->tfa = $tfa;
        $this->session = $session;
        $this->pageFactory = $pageFactory;
        $this->userConfigManager = $userConfigManager;
        $this->tokenVerifier = $tokenVerifier;
        $this->duoSecurity = $duoSecurity;
        $this->messageManager = $context->getMessageManager();
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->tfaProviderSession = $tfaProviderSession
            ?: ObjectManager::getInstance()->get(TfaProviderSessionInterface::class);
    }

    /**
     * Get current user
     *
     * @return \Magento\User\Model\User|null
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
     */
    protected function _isAllowed()
    {
        if (!parent::_isAllowed()) {
            return false;
        }

        // 1st time users must have the token.
        $user = $this->getUser();

        return
            $user &&
            $this->tfa->getProviderIsAllowed((int)$user->getId(), DuoSecurity::CODE)
            && (
                $this->userConfigManager->isProviderConfigurationActive((int)$user->getId(), DuoSecurity::CODE)
                || $this->tokenVerifier->isConfigTokenProvided()
            );
    }
}

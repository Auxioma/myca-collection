<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Google;

use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractConfigureAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\Google;
use Magento\User\Model\User;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;

/**
 * Google authenticator configuration page
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Configure extends AbstractConfigureAction implements HttpGetActionInterface
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
     * @param Action\Context $context
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param TfaInterface $tfa
     * @param HtmlAreaTokenVerifier $tokenVerifier
     */
    public function __construct(
        Action\Context $context,
        Session $session,
        PageFactory $pageFactory,
        TfaInterface $tfa,
        HtmlAreaTokenVerifier $tokenVerifier
    ) {
        parent::__construct($context, $tokenVerifier);
        $this->tfa = $tfa;
        $this->session = $session;
        $this->pageFactory = $pageFactory;
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
        if (!parent::_isAllowed()) {
            return false;
        }

        $user = $this->getUser();

        return
            $user &&
            $this->tfa->getProviderIsAllowed((int) $user->getId(), Google::CODE) &&
            !$this->tfa->getProvider(Google::CODE)->isActive((int) $user->getId());
    }
}

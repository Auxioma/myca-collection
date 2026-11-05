<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\U2f;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractConfigureAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\U2fKey;
use Magento\TwoFactorAuth\Model\Tfa;
use Magento\User\Model\User;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;

/**
 * Configuration page for U2f
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Configure extends AbstractConfigureAction implements HttpGetActionInterface
{
    /**
     * @var Tfa
     */
    private Tfa $tfa;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @var PageFactory
     */
    private PageFactory $pageFactory;

    /**
     * @param Tfa $tfa
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param Context $context
     * @param HtmlAreaTokenVerifier $tokenVerifier
     */
    public function __construct(
        Tfa $tfa,
        Session $session,
        PageFactory $pageFactory,
        Context $context,
        HtmlAreaTokenVerifier $tokenVerifier
    ) {

        $this->tfa = $tfa;
        $this->session = $session;
        parent::__construct($context, $tokenVerifier);
        $this->pageFactory = $pageFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        return $this->pageFactory->create();
    }

    /**
     * Get the current user
     *
     * @return User|null
     */
    private function getUser(): ?User
    {
        return $this->session->getUser();
    }

    /**
     * C@inheritDoc
     */
    protected function _isAllowed()
    {
        if (!parent::_isAllowed()) {
            return false;
        }

        $user = $this->getUser();

        return
            $user &&
            $this->tfa->getProviderIsAllowed((int) $user->getId(), U2fKey::CODE) &&
            !$this->tfa->getProvider(U2fKey::CODE)->isActive((int) $user->getId());
    }
}

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
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Model\AlertInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\DuoSecurity;
use Magento\TwoFactorAuth\Api\UserConfigManagerInterface;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;
use Magento\User\Model\User;

/**
 * Duo security authentication post controller
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class VerifyIdentityPost extends AbstractAction implements HttpGetActionInterface
{
    /**
     * Authpost constructor.
     * @param Context $context
     * @param Session $session
     * @param DuoSecurity $duoSecurity
     * @param DataObjectFactory $dataObjectFactory
     * @param AlertInterface $alert
     * @param TfaInterface $tfa
     * @param HtmlAreaTokenVerifier $tokenVerifier
     * @param UserConfigManagerInterface $userConfig
     * @param TfaProviderSessionInterface $tfaProviderSession
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly DuoSecurity $duoSecurity,
        private readonly DataObjectFactory $dataObjectFactory,
        private readonly AlertInterface $alert,
        private readonly TfaInterface $tfa,
        private readonly HtmlAreaTokenVerifier $tokenVerifier,
        private readonly UserConfigManagerInterface $userConfig,
        private readonly TfaProviderSessionInterface $tfaProviderSession
    ) {
        parent::__construct($context);
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
        $username = $user->getUserName();
        $savedState = $this->session->getDuoState();

        if (!empty($savedState) && !empty($username) && ($this->getRequest()->getParam('state') == $savedState)) {
            $this->duoSecurity->setUserIdentityCallbackUrl('tfa/duo/verifyidentitypost');

            if ($this->duoSecurity->verify($user, $this->dataObjectFactory->create([
                'data' => $this->getRequest()->getParams(),
            ]))) {
                $this->tfa->getProvider(DuoSecurity::CODE)->activate((int) $user->getId());
                $this->tfaProviderSession->setNewProviderConfigurationAllowed($this->tfaProviderSession::ALLOW);

                return $this->_redirect(
                    $this->tfa->getProviderByCode(
                        $this->tfaProviderSession->getProviderToConfigure()
                    )->getConfigureAction()
                );
            }
        } else {
            $this->alert->event(
                'Magento_TwoFactorAuth',
                'DuoSecurity invalid auth',
                AlertInterface::LEVEL_WARNING,
                $user->getUserName()
            );

            return $this->_redirect('*/*/verifyidentity');
        }

        return $this->_redirect('*/*/verifyidentity');
    }

    /**
     * @inheritDoc
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
            $this->tfa->getProviderIsAllowed((int)$user->getId(), DuoSecurity::CODE)
            && (
                $this->userConfig->isProviderConfigurationActive((int)$user->getId(), DuoSecurity::CODE)
                || $this->tokenVerifier->isConfigTokenProvided()
            );
    }
}

<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Authy;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\TwoFactorAuth\Model\AlertInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractConfigureAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\Authy;
use Magento\TwoFactorAuth\Model\Provider\Engine\Authy\Verification;
use Magento\User\Model\User;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;
use Magento\TwoFactorAuth\Api\UserConfigManagerInterface;

/**
 * Verify authy
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Configureverifypost extends AbstractConfigureAction implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @var TfaInterface
     */
    private TfaInterface $tfa;

    /**
     * @var Authy
     */
    private Authy $authy;

    /**
     * @var TfaSessionInterface
     */
    private TfaSessionInterface $tfaSession;

    /**
     * @var AlertInterface
     */
    private AlertInterface $alert;

    /**
     * @var Authy\Verification
     */
    private Verification $verification;

    /**
     * @var UserConfigManagerInterface
     */
    private $userConfigManager;

    /**
     * @param Context $context
     * @param Session $session
     * @param TfaInterface $tfa
     * @param TfaSessionInterface $tfaSession
     * @param AlertInterface $alert
     * @param Authy $authy
     * @param Verification $verification
     * @param JsonFactory $jsonFactory
     * @param HtmlAreaTokenVerifier $tokenVerifier
     * @param UserConfigManagerInterface|null $userConfigManager
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Action\Context $context,
        Session $session,
        TfaInterface $tfa,
        TfaSessionInterface $tfaSession,
        AlertInterface $alert,
        Authy $authy,
        Authy\Verification $verification,
        JsonFactory $jsonFactory,
        HtmlAreaTokenVerifier $tokenVerifier,
        ?UserConfigManagerInterface $userConfigManager = null
    ) {
        parent::__construct($context, $tokenVerifier);
        $this->jsonFactory = $jsonFactory;
        $this->session = $session;
        $this->tfa = $tfa;
        $this->tfaSession = $tfaSession;
        $this->alert = $alert;
        $this->verification = $verification;
        $this->authy = $authy;
        $this->userConfigManager = $userConfigManager
            ?: ObjectManager::getInstance()->get(UserConfigManagerInterface::class);
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
     * @inheritdoc
     */
    public function execute()
    {
        $verificationCode = $this->getRequest()->getParam('tfa_verify');
        $response = $this->jsonFactory->create();

        try {
            $this->verification->verify($this->getUser(), $verificationCode);
            $this->authy->enroll($this->getUser());
            $this->tfaSession->grantAccess();

            $this->alert->event(
                'Magento_TwoFactorAuth',
                'Authy identity verified',
                AlertInterface::LEVEL_INFO,
                $this->getUser()->getUserName()
            );

            $response->setData([
                'success' => true,
            ]);
            $this->userConfigManager->setDefaultProvider((int) $this->getUser()->getId(), Authy::CODE);
        } catch (Exception $e) {
            $this->alert->event(
                'Magento_TwoFactorAuth',
                'Authy identity verification failure',
                AlertInterface::LEVEL_ERROR,
                $this->getUser()->getUserName(),
                $e->getMessage()
            );

            $response->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed()
    {
        if (!parent::_isAllowed()) {
            return false;
        }

        $user = $this->getUser();

        return
            $user &&
            $this->tfa->getProviderIsAllowed((int) $user->getId(), Authy::CODE) &&
            !$this->tfa->getProvider(Authy::CODE)->isActive((int) $user->getId());
    }
}

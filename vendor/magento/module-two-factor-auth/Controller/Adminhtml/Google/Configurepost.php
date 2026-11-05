<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Google;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TwoFactorAuth\Model\AlertInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractConfigureAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\Google;
use Magento\User\Model\User;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;
use Magento\TwoFactorAuth\Api\UserConfigManagerInterface;

/**
 * Google authenticator configuration post controller
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Configurepost extends AbstractConfigureAction implements HttpPostActionInterface
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
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var Google
     */
    private Google $google;

    /**
     * @var TfaSessionInterface
     */
    private TfaSessionInterface $tfaSession;

    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @var AlertInterface
     */
    private AlertInterface $alert;

    /**
     * @var UserConfigManagerInterface
     */
    private mixed $userConfigManager;

    /**
     * @param Context $context
     * @param Session $session
     * @param JsonFactory $jsonFactory
     * @param Google $google
     * @param TfaSessionInterface $tfaSession
     * @param TfaInterface $tfa
     * @param AlertInterface $alert
     * @param DataObjectFactory $dataObjectFactory
     * @param HtmlAreaTokenVerifier $tokenVerifier
     * @param UserConfigManagerInterface|null $userConfigManager
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Action\Context $context,
        Session $session,
        JsonFactory $jsonFactory,
        Google $google,
        TfaSessionInterface $tfaSession,
        TfaInterface $tfa,
        AlertInterface $alert,
        DataObjectFactory $dataObjectFactory,
        HtmlAreaTokenVerifier $tokenVerifier,
        ?UserConfigManagerInterface $userConfigManager = null
    ) {
        parent::__construct($context, $tokenVerifier);
        $this->tfa = $tfa;
        $this->session = $session;
        $this->jsonFactory = $jsonFactory;
        $this->google = $google;
        $this->tfaSession = $tfaSession;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->alert = $alert;
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
     *
     * @return ResponseInterface|ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $response = $this->jsonFactory->create();

        $user = $this->getUser();

        if ($this->google->verify($user, $this->dataObjectFactory->create([
            'data' => $this->getRequest()->getParams(),
        ]))) {
            $this->tfa->getProvider(Google::CODE)->activate((int) $user->getId());
            $this->tfaSession->grantAccess();

            $this->alert->event(
                'Magento_TwoFactorAuth',
                'New Google Authenticator code issued',
                AlertInterface::LEVEL_INFO,
                $user->getUserName()
            );

            $response->setData([
                'success' => true,
            ]);
            $this->userConfigManager->setDefaultProvider((int) $this->getUser()->getId(), Google::CODE);
        } else {
            $response->setData([
                'success' => false,
                'message' => 'Invalid code',
            ]);
        }

        return $response;
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

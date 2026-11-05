<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Authy;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Model\AlertInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\Authy;
use Magento\User\Model\User;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\Framework\App\ObjectManager;

/**
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class VerifyIdentityPost extends AbstractAction implements HttpPostActionInterface
{
    /**
     * Config path for the 2FA Attempts
     */
    private const XML_PATH_2FA_RETRY_ATTEMPTS = 'twofactorauth/general/twofactorauth_retry';

    /**
     * Config path for the 2FA auth lock expire
     */
    private const XML_PATH_2FA_LOCK_EXPIRE = 'twofactorauth/general/auth_lock_expire';

    /**
     * @param Context $context
     * @param Session $session
     * @param JsonFactory $jsonFactory
     * @param Authy $authy
     * @param TfaInterface $tfa
     * @param AlertInterface $alert
     * @param DataObjectFactory $dataObjectFactory
     * @param UserResource $userResource
     * @param ScopeConfigInterface $scopeConfig
     * @param TfaProviderSessionInterface $tfaProviderSession
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly JsonFactory $jsonFactory,
        private readonly Authy $authy,
        private readonly TfaInterface $tfa,
        private readonly AlertInterface $alert,
        private readonly DataObjectFactory $dataObjectFactory,
        private readonly UserResource $userResource,
        private readonly ScopeConfigInterface $scopeConfig,
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
     * @inheritdoc
     */
    public function execute()
    {
        $user = $this->getUser();
        $result = $this->jsonFactory->create();

        try {
            if (!$this->allowApiRetries()) {
                $lockThreshold = $this->scopeConfig->getValue(self::XML_PATH_2FA_LOCK_EXPIRE);
                if ($this->userResource->lock((int)$user->getId(), 0, $lockThreshold)) {
                    $result->setData(['success' => false, 'message' => "Your account is temporarily disabled."]);
                    return $result;
                }
            }
            $this->authy->verify($user, $this->dataObjectFactory->create([
                'data' => $this->getRequest()->getParams(),
            ]));
            $result->setData(['success' => true]);
            $this->tfaProviderSession->setNewProviderConfigurationAllowed($this->tfaProviderSession::ALLOW);

        } catch (Exception $e) {
            $this->alert->event(
                'Magento_TwoFactorAuth',
                'Authy error',
                AlertInterface::LEVEL_ERROR,
                $this->getUser()->getUserName(),
                $e->getMessage()
            );

            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed()
    {
        $user = $this->getUser();

        return $user && $this->isUserAllowed($user);
    }

    /**
     * Check if retry attempt above threshold value
     *
     * @return bool
     */
    private function allowApiRetries() : bool
    {
        $maxRetries = $this->scopeConfig->getValue(self::XML_PATH_2FA_RETRY_ATTEMPTS);
        $verifyAttempts = $this->session->getOtpAttempt();
        $verifyAttempts = $verifyAttempts === null ? 1 : $verifyAttempts+1;
        $this->session->setOtpAttempt($verifyAttempts);

        return  $maxRetries >= $verifyAttempts;
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
            $this->tfa->getProviderIsAllowed((int) $user->getId(), Authy::CODE) &&
            $this->tfa->getProvider(Authy::CODE)->isActive((int) $user->getId());
    }
}

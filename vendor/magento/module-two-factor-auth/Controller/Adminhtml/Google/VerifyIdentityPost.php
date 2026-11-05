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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\AlertInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Model\Provider\Engine\Google;
use Magento\User\Model\User;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;

/**
 * Google authenticator configuration post controller
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class VerifyIdentityPost extends AbstractAction implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param Session $session
     * @param JsonFactory $jsonFactory
     * @param Google $google
     * @param TfaInterface $tfa
     * @param AlertInterface $alert
     * @param DataObjectFactory $dataObjectFactory
     * @param TfaProviderSessionInterface $tfaProviderSession
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Action\Context $context,
        private readonly Session $session,
        private readonly JsonFactory $jsonFactory,
        private readonly Google $google,
        private readonly TfaInterface $tfa,
        private readonly AlertInterface $alert,
        private readonly DataObjectFactory $dataObjectFactory,
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
            $response->setData([
                'success' => true,
            ]);
            $this->tfaProviderSession->setNewProviderConfigurationAllowed($this->tfaProviderSession::ALLOW);
        } else {
            $this->alert->event(
                'Magento_TwoFactorAuth',
                'Google auth invalid token',
                AlertInterface::LEVEL_WARNING,
                $user->getUserName()
            );

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
            $this->tfa->getProviderIsAllowed((int)$user->getId(), Google::CODE)
            && $this->tfa->getProvider(Google::CODE)->isActive((int)$user->getId());
    }
}

<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\U2f;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Model\AlertInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Model\Provider\Engine\U2fKey;
use Magento\TwoFactorAuth\Model\Provider\Engine\U2fKey\Session as U2fSession;
use Magento\TwoFactorAuth\Model\Tfa;
use Magento\User\Model\User;

/**
 * U2f key Authentication post controller
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class VerifyIdentityPost extends AbstractAction implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param Tfa $tfa
     * @param Session $session
     * @param JsonFactory $jsonFactory
     * @param U2fKey $u2fKey
     * @param U2fSession $u2fSession
     * @param DataObjectFactory $dataObjectFactory
     * @param AlertInterface $alert
     * @param TfaProviderSessionInterface $tfaProviderSession
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        Action\Context $context,
        private readonly Tfa $tfa,
        private readonly Session $session,
        private readonly JsonFactory $jsonFactory,
        private readonly U2fKey $u2fKey,
        private readonly U2fSession $u2fSession,
        private readonly DataObjectFactory $dataObjectFactory,
        private readonly AlertInterface $alert,
        private readonly TfaProviderSessionInterface $tfaProviderSession
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $challenge = $this->u2fSession->getU2fChallenge();
            if (!empty($challenge)) {
                $this->u2fKey->verify($this->getUser(), $this->dataObjectFactory->create([
                    'data' => [
                        'publicKeyCredential' => $this->getRequest()->getParams()['publicKeyCredential'],
                        'originalChallenge' => $challenge
                    ]
                ]));
                $this->u2fSession->setU2fChallenge(null);

                $res = ['success' => true];
                $this->tfaProviderSession->setNewProviderConfigurationAllowed($this->tfaProviderSession::ALLOW);
            } else {
                $res = ['success' => false];
            }
        } catch (Exception $e) {
            $this->alert->event(
                'Magento_TwoFactorAuth',
                'U2F error',
                AlertInterface::LEVEL_ERROR,
                $this->getUser()->getUserName(),
                $e->getMessage()
            );

            $res = ['success' => false, 'message' => $e->getMessage()];
        }

        $result->setData($res);
        return $result;
    }

    /**
     * Retrieve the current authenticated user
     *
     * @return User|null
     */
    private function getUser(): ?User
    {
        return $this->session->getUser();
    }

    /**
     * Check if admin has permissions to visit related pages
     *
     * @return bool
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
            $this->tfa->getProviderIsAllowed((int) $user->getId(), U2fKey::CODE) &&
            $this->tfa->getProvider(U2fKey::CODE)->isActive((int) $user->getId());
    }
}

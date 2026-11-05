<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Tfa;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;
use Magento\TwoFactorAuth\Api\UserConfigManagerInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\TwoFactorAuth\Api\UserConfigRequestManagerInterface;

/**
 * 2FA entry point controller
 */
class Index extends AbstractAction implements HttpGetActionInterface
{
    // To give the email link a place to set the token without causing a loop
    /**
     * @var string[]
     */
    protected $_publicActions = ['index'];

    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_TwoFactorAuth::tfa';

    /**
     * @var TfaInterface
     */
    private TfaInterface $tfa;

    /**
     * @var TfaSessionInterface
     */
    private $session;

    /**
     * @var UserConfigManagerInterface
     */
    private UserConfigManagerInterface $userConfigManager;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var UserConfigRequestManagerInterface
     */
    private $userConfigRequest;

    /**
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @var TfaProviderSessionInterface
     */
    private TfaProviderSessionInterface $tfaProviderSession;

    /**
     * @param Context $context
     * @param TfaSessionInterface $session
     * @param UserConfigManagerInterface $userConfigManager
     * @param TfaInterface $tfa
     * @param UserConfigRequestManagerInterface $userConfigRequestManager
     * @param UserContextInterface $userContext
     * @param TfaProviderSessionInterface|null $tfaProviderSession
     */
    public function __construct(
        Context $context,
        TfaSessionInterface $session,
        UserConfigManagerInterface $userConfigManager,
        TfaInterface $tfa,
        UserConfigRequestManagerInterface $userConfigRequestManager,
        UserContextInterface $userContext,
        ?TfaProviderSessionInterface $tfaProviderSession = null
    ) {
        parent::__construct($context);
        $this->tfa = $tfa;
        $this->session = $session;
        $this->userConfigManager = $userConfigManager;
        $this->context = $context;
        $this->userConfigRequest = $userConfigRequestManager;
        $this->userContext = $userContext;
        $this->tfaProviderSession = $tfaProviderSession
            ?: ObjectManager::getInstance()->get(TfaProviderSessionInterface::class);
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $userId = $this->userContext->getUserId();
        if (!$this->tfa->getUserProviders($userId)) {
            //If 2FA is not configured - request configuration.
            return $this->_redirect('tfa/tfa/requestconfig');
        }
        $providerCode = '';
        $defaultProviderCode = $this->userConfigManager->getDefaultProvider($userId);

        if ($this->tfa->getProviderIsAllowed($userId, $defaultProviderCode)
            && $this->tfa->getProvider($defaultProviderCode)->isActive($userId)
        ) {
            //If default provider was configured - select it.
            $providerCode = $defaultProviderCode;
            $provider = $this->tfa->getProvider($providerCode);
            if ($provider) {
                //Provider found, user will be challenged.
                return $this->_redirect($provider->getAuthAction());
            }
        }

        if (!$providerCode) {
            $providersToConfigure = $this->tfa->getAllEnabledProviders();

            foreach ($providersToConfigure as $toActivateProvider) {
                if ($toActivateProvider->isActive($userId) &&
                    $this->tfa->getProviderIsAllowed($userId, $toActivateProvider->getCode())) {
                    return $this->_redirect($toActivateProvider->getAuthAction());
                }
            }

            $providersToConfigure = $this->tfa->getProvidersToActivate($userId);
            foreach ($providersToConfigure as $toActivateProvider) {
                $this->tfaProviderSession->setNewProviderConfigurationAllowed(
                    TfaProviderSessionInterface::ALLOW
                );
                return $this->_redirect($toActivateProvider->getConfigureAction());
            }
        }

        throw new LocalizedException(__('Internal error accessing 2FA index page'));
    }
}

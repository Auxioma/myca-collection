<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Observer;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\App\AbstractAction;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\Tfa\Index;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;
use Magento\TwoFactorAuth\Api\UserConfigRequestManagerInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\Tfa\Requestconfig;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;
use Magento\TwoFactorAuth\Model\Config\UserNotifier;
use Magento\TwoFactorAuth\Api\UserConfigTokenManagerInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;

/**
 * Handle redirection to 2FA page if required
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ControllerActionPredispatch implements ObserverInterface
{
    /**
     * @var TfaInterface
     */
    private TfaInterface $tfa;

    /**
     * @var TfaSessionInterface
     */
    private TfaSessionInterface $tfaSession;

    /**
     * @var UserConfigRequestManagerInterface
     */
    private $configRequestManager;

    /**
     * @var AbstractAction|null
     */
    private $action;

    /**
     * @var HtmlAreaTokenVerifier
     */
    private HtmlAreaTokenVerifier $tokenManager;

    /**
     * @var ActionFlag
     */
    private ActionFlag $actionFlag;

    /**
     * @var UrlInterface
     */
    private UrlInterface $url;

    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;

    /**
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @var UserNotifier
     */
    private UserNotifier $userNotifier;

    /**
     * @var UserConfigTokenManagerInterface
     */
    private UserConfigTokenManagerInterface $userConfigTokenManagerInterface;

    /**
     * @var TfaProviderSessionInterface
     */
    private TfaProviderSessionInterface $tfaProviderSessionInterface;

    /**
     * @param TfaInterface $tfa
     * @param TfaSessionInterface $tfaSession
     * @param UserConfigRequestManagerInterface $configRequestManager
     * @param HtmlAreaTokenVerifier $tokenManager
     * @param ActionFlag $actionFlag
     * @param UrlInterface $url
     * @param AuthorizationInterface $authorization
     * @param UserContextInterface $userContext
     * @param UserNotifier|null $userNotifier
     * @param UserConfigTokenManagerInterface|null $userConfigTokenManagerInterface
     * @param TfaProviderSessionInterface|null $tfaProviderSessionInterface
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        TfaInterface $tfa,
        TfaSessionInterface $tfaSession,
        UserConfigRequestManagerInterface $configRequestManager,
        HtmlAreaTokenVerifier $tokenManager,
        ActionFlag $actionFlag,
        UrlInterface $url,
        AuthorizationInterface $authorization,
        UserContextInterface $userContext,
        ?UserNotifier $userNotifier = null,
        ?UserConfigTokenManagerInterface $userConfigTokenManagerInterface = null,
        ?TfaProviderSessionInterface $tfaProviderSessionInterface = null
    ) {
        $this->tfa = $tfa;
        $this->tfaSession = $tfaSession;
        $this->configRequestManager = $configRequestManager;
        $this->tokenManager = $tokenManager;
        $this->actionFlag = $actionFlag;
        $this->url = $url;
        $this->authorization = $authorization;
        $this->userContext = $userContext;
        $this->userNotifier = $userNotifier ?: ObjectManager::getInstance()->get(UserNotifier::class);
        $this->userConfigTokenManagerInterface = $userConfigTokenManagerInterface ?:
            ObjectManager::getInstance()->get(UserConfigTokenManagerInterface::class);
        $this->tfaProviderSessionInterface = $tfaProviderSessionInterface ?:
            ObjectManager::getInstance()->get(TfaProviderSessionInterface::class);
    }

    /**
     * Redirect user to given URL.
     *
     * @param string $url
     * @return void
     */
    private function redirect(string $url): void
    {
        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
        $this->action->getResponse()->setRedirect($this->url->getUrl($url));
    }

    /**
     * @inheritDoc
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(Observer $observer)
    {
        /** @var $controllerAction AbstractAction */
        $controllerAction = $observer->getEvent()->getData('controller_action');
        $this->action = $controllerAction;
        $fullActionName = $observer->getEvent()->getData('request')->getFullActionName();
        $userId = $this->userContext->getUserId();

        $this->tokenManager->readConfigToken();

        if (in_array($fullActionName, $this->tfa->getAllowedUrls(), true)) {
            //Actions that are used for 2FA must remain accessible.
            return;
        }

        if ($userId) {
            $userProviders = $this->tfa->getUserProviders($userId);
            $activatedProvider = [];

            foreach ($userProviders as $userProvider) {
                if ($userProvider->isActive($userId)) {
                    $activatedProvider[] = $userProvider; //list of all activated providers of user
                }
            }
            $accessGranted = $this->tfaSession->isGranted();

            if (!$accessGranted && !empty($userProviders)) {
                //User needs special link with a token to be allowed to configure 2FA
                if ($this->authorization->isAllowed(Requestconfig::ADMIN_RESOURCE)) {
                    if (empty($activatedProvider)) {
                        $this->tfaProviderSessionInterface->setNewProviderConfigurationAllowed(
                            TfaProviderSessionInterface::ALLOW
                        );
                        $this->redirect('tfa/tfa/requestconfig');
                    } else {
                        $url = $this->userNotifier->getPersonalRequestConfigUrl(
                            $this->userConfigTokenManagerInterface->issueFor($userId)
                        );
                        $this->redirect($url);
                    }
                } else {
                    $this->redirect('tfa/tfa/accessdenied');
                }
            } else {
                if (!$accessGranted) {
                    if ($this->authorization->isAllowed(Index::ADMIN_RESOURCE)) {
                        $this->redirect('tfa/tfa/index');
                    } else {
                        $this->redirect('tfa/tfa/accessdenied');
                    }
                }
            }
        }
    }
}

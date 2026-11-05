<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml\Tfa;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\ObjectManager;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use Magento\Backend\Model\Auth\Session;

/**
 * Verify user identity for the application.
 */
class ProviderSelection extends AbstractAction implements HttpGetActionInterface
{
    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_TwoFactorAuth::tfa';

    /**
     * @param Context $context
     * @param TfaInterface $tfa
     * @param Session $session
     * @param TfaProviderSessionInterface $tfaProviderSessionInterface
     */
    public function __construct(
        Context $context,
        private readonly TfaInterface $tfa,
        private readonly Session $session,
        private readonly TfaProviderSessionInterface $tfaProviderSessionInterface
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function dispatch(RequestInterface $request)
    {
        $user = $this->session->getUser();
        if ($user && $provider = $this->getRequest()->getParam('provider')) {
            $toActivate = $this->tfa->getProvidersToActivate((int)$user->getId());

            foreach ($toActivate as $toActivateProvider) {
                if ($toActivateProvider->getCode() === $provider) {
                    if (!$this->tfaProviderSessionInterface->isNewProviderConfigurationAllowed()) {
                        $this->tfaProviderSessionInterface->setProviderToConfigure($provider);
                        return parent::dispatch($request);
                    }

                    return $this->_redirect($toActivateProvider->getConfigureAction());
                }
            }
        }

        return $this->_redirect('tfa/tfa/requestconfig');
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}

<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\TwoFactorAuth\Model\UserConfig\HtmlAreaTokenVerifier;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;

/**
 * Base action class for controllers related to 2FA provider configuration.
 */
abstract class AbstractConfigureAction extends AbstractAction
{
    /**
     * @var HtmlAreaTokenVerifier
     */
    private HtmlAreaTokenVerifier $tokenVerifier;

    /**
     * @var TfaProviderSessionInterface
     */
    private TfaProviderSessionInterface $tfaProviderSession;

    /**
     * @param Context $context
     * @param HtmlAreaTokenVerifier $tokenVerifier
     * @param TfaProviderSessionInterface|null $tfaProviderSession
     */
    public function __construct(
        Context $context,
        HtmlAreaTokenVerifier $tokenVerifier,
        ?TfaProviderSessionInterface $tfaProviderSession = null
    ) {
        parent::__construct($context);
        $this->tokenVerifier = $tokenVerifier;
        $this->tfaProviderSession = $tfaProviderSession
            ?: ObjectManager::getInstance()->get(TfaProviderSessionInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function _isAllowed()
    {
        $isAllowed = parent::_isAllowed();
        if ($isAllowed) {
            $isAllowed = $this->tokenVerifier->isConfigTokenProvided();
        }

        return $isAllowed;
    }

    /**
     * Dispatch before execute
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function dispatch(RequestInterface $request)
    {
        if ($this->tfaProviderSession->isNewProviderConfigurationAllowed()) {
            return parent::dispatch($request);
        }
        return $this->_redirect('tfa/tfa/requestconfig');
    }
}

<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Block\Provider\U2fKey;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Model\Provider\Engine\U2fKey;
use Magento\TwoFactorAuth\Model\Provider\Engine\U2fKey\Session as U2fSession;

/**
 * Verify Identity using 2FA
 * @api
 */
class VerifyIdentity extends Template
{
    /**
     * @param Context $context
     * @param U2fSession $u2fSession
     * @param U2fKey $u2fKey
     * @param Session $session
     * @param TfaProviderSessionInterface $tfaProviderSession
     * @param TfaInterface $tfa
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        private readonly U2fSession $u2fSession,
        private readonly U2fKey $u2fKey,
        private readonly Session $session,
        private readonly TfaProviderSessionInterface $tfaProviderSession,
        private readonly TfaInterface $tfa,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function getJsLayout()
    {
        $this->jsLayout['components']['tfa-auth']['postUrl'] =
            $this->getUrl('*/*/verifyidentitypost');

        $this->jsLayout['components']['tfa-auth']['successUrl'] =
            $this->getUrl(
                $this->tfa->getProviderByCode(
                    $this->tfaProviderSession->getProviderToConfigure()
                )->getConfigureAction()
            );

        $this->jsLayout['components']['tfa-auth']['touchImageUrl'] =
            $this->getViewFileUrl('Magento_TwoFactorAuth::images/u2f/touch.png');

        $this->jsLayout['components']['tfa-auth']['authenticateData'] = $this->generateAuthenticateData();
        return parent::getJsLayout();
    }

    /**
     * Get the data needed to authenticate a webauthn request
     *
     * @return array
     */
    private function generateAuthenticateData(): array
    {
        $authenticateData = $this->u2fKey->getAuthenticateData($this->session->getUser());
        $this->u2fSession->setU2fChallenge($authenticateData['credentialRequestOptions']['challenge']);

        return $authenticateData;
    }
}

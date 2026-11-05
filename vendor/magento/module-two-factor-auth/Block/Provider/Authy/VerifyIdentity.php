<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Block\Provider\Authy;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;

/**
 * Verify Identity using 2FA
 * @api
 */
class VerifyIdentity extends Template
{
    /**
     * @param Context $context
     * @param TfaProviderSessionInterface $tfaProviderSession
     * @param TfaInterface $tfa
     * @param array $data
     */
    public function __construct(
        Context $context,
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

        $this->jsLayout['components']['tfa-auth']['tokenRequestUrl'] =
            $this->getUrl('*/*/token');

        $this->jsLayout['components']['tfa-auth']['oneTouchUrl'] =
            $this->getUrl('*/*/onetouch');

        $this->jsLayout['components']['tfa-auth']['verifyOneTouchUrl'] =
            $this->getUrl('*/*/verifyonetouch');

        $this->jsLayout['components']['tfa-auth']['successUrl'] =
            $this->getUrl(
                $this->tfa->getProviderByCode(
                    $this->tfaProviderSession->getProviderToConfigure()
                )->getConfigureAction()
            );

        return parent::getJsLayout();
    }
}

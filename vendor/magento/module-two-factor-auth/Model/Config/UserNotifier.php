<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Backend\Model\UrlInterface;

/**
 * Represents configuration for notifying the user
 */
class UserNotifier
{
    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param UrlInterface $url
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        UrlInterface $url,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
    }

    /**
     * Get the url to send to the user for configuring personal 2fa settings
     *
     * @param string $tfaToken
     * @return string
     */
    public function getPersonalRequestConfigUrl(string $tfaToken): string
    {
        return $this->getRequestConfigUrl($tfaToken);
    }

    /**
     * Get the url to send to the user for configuring global 2fa settings
     *
     * @param string $tfaToken
     * @return string
     */
    public function getAppRequestConfigUrl(string $tfaToken): string
    {
        return $this->getRequestConfigUrl($tfaToken);
    }

    /**
     * Get the default config url
     *
     * @param string $tfaToken
     * @return string
     */
    private function getRequestConfigUrl(string $tfaToken)
    {
        return $this->url->getUrl('tfa/tfa/index', ['tfat' => $tfaToken]);
    }

    /**
     * Get the url to send to the user for configuring new 2fa provider
     *
     * @param string $tfaToken
     * @return string
     */
    public function getIdentityVerificationUrl(string $tfaToken): string
    {
        return $this->url->getUrl('tfa/tfa/verifyidentityrequest', ['tfat' => $tfaToken]);
    }
}

<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\ViewModel;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\UrlInterface;

/**
 * ViewModel with providers list allowing to verify identity of user.
 */
class ProviderSelection implements ArgumentInterface
{
    /**
     * @param TfaInterface $tfa
     * @param Session $session
     * @param Repository $asset
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly TfaInterface $tfa,
        private readonly Session $session,
        private readonly Repository $asset,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Create list of providers for user to choose.
     *
     * @return array
     */
    public function getProvidersList(): array
    {
        $user = $this->session->getUser();
        $providers = $this->tfa->getUserProviders((int) $user->getId());
        $list = [];
        foreach ($providers as $provider) {
            if ($provider->isActive((int) $user->getId())) {
                $list[] = [
                    'code' => $provider->getCode(),
                    'name' => $provider->getName(),
                    'icon' => $this->asset->getUrl($provider->getIcon()),
                    'url' => $this->url->getUrl($provider->getExtraActions()['verifyidentity'] ?? '')
                ];
            }
        }

        return $list;
    }

    /**
     * Get the form's action URL.
     *
     * @return string
     */
    public function getActionUrl(): string
    {
        return $this->url->getUrl('tfa/tfa/verifyidentity');
    }
}

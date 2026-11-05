<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Api;

/**
 * 2FA provider configuration session interface
 */
interface TfaProviderSessionInterface
{
    /**
     * Provider configuration status
     */
    public const FLAG = 'flag';

    /**
     * Provider code which needs to set in storage
     */
    public const PROVIDER_CODE = 'provider';

    /**
     * Provider allow to configure status
     */
    public const ALLOW = true;

    /**
     * Provider not allow to configure status
     */
    public const DISALLOW = false;

    /**
     * Set the status for new provider configuration
     *
     * @param bool $status
     */
    public function setNewProviderConfigurationAllowed(bool $status): void;

    /**
     * Get the status of new provider configuration
     */
    public function isNewProviderConfigurationAllowed(): bool;

    /**
     * Set the provider which needs to be configured post user identity verification
     *
     * @param string $provider
     */
    public function setProviderToConfigure(string $provider): void;

    /**
     * Get the provider which needs to be configured post user identity verification
     */
    public function getProviderToConfigure(): ?string;
}

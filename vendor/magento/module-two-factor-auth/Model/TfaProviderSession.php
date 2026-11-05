<?php
/**
 * Copyright 2025 Adobe.
 * All rights reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Model;

use Magento\Framework\Session\SessionManager;
use Magento\TwoFactorAuth\Api\TfaProviderSessionInterface;

/**
 * @inheritDoc
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class TfaProviderSession extends SessionManager implements TfaProviderSessionInterface
{
    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isNewProviderConfigurationAllowed(): bool
    {
        return $this->getData(static::FLAG) ?? self::DISALLOW;
    }

    /**
     * @inheritDoc
     */
    public function setNewProviderConfigurationAllowed(bool $status): void
    {
        $this->storage->setData(static::FLAG, $status);
    }

    /**
     * @inheritDoc
     *
     * @return string|null
     */
    public function getProviderToConfigure(): ?string
    {
        return $this->getData(static::PROVIDER_CODE) ?? null;
    }

    /**
     * @inheritDoc
     */
    public function setProviderToConfigure(string $provider): void
    {
        $this->storage->setData(static::PROVIDER_CODE, $provider);
    }
}

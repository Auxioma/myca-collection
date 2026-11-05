<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Model\Provider\Engine\Google;

use OTPHP\TOTP;
use OTPHP\TOTPInterface;

/**
 * Creates a TOTP instance
 */
class TotpFactory
{
    /**
     * Create a TOTP instance
     *
     * @param string $secret
     * @return TOTPInterface
     */
    public function create(string $secret): TOTPInterface
    {
        return TOTP::create($secret);
    }
}

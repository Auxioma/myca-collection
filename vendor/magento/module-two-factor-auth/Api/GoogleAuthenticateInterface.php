<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\TwoFactorAuth\Api;

/**
 * Represents google authentication
 *
 * @api
 */
interface GoogleAuthenticateInterface
{
    /**
     * Get an admin token by authenticating using google
     *
     * @param string $username
     * @param string $password
     * @param string $otp
     * @return string
     */
    public function createAdminAccessToken(string $username, string $password, string $otp): string;
}

<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaUi\Model;

use Magento\Framework\Exception\InputException;

/**
 * reCAPTCHA type resolver
 *
 * @api
 */
interface CaptchaTypeResolverInterface
{
    /**
     * Get reCAPTCHA type for specific functionality. Return NULL id reCAPTCHA is disabled for this functionality
     *
     * @param string $key Functionality identifier (like customer login, contact)
     * @return string|null
     * @throws InputException
     */
    public function getCaptchaTypeFor(string $key): ?string;
}

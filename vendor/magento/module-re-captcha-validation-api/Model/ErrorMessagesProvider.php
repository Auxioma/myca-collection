<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaValidationApi\Model;

/**
 * Extension point for adding reCAPTCHA technical errors.
 *
 * @api Class name should be used in DI for adding new technical errors.
 */
class ErrorMessagesProvider
{
    /**
     * @var array
     */
    private $errors;

    /**
     * @param array $errors
     */
    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    /**
     * Get error label
     *
     * @param string $key
     * @return string
     */
    public function getErrorMessage(string $key): string
    {
        return $this->errors[$key] ?? $key;
    }
}

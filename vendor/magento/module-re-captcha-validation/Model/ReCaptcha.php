<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaValidation\Model;

use ReCaptcha\ReCaptcha as GoogleReCaptcha;

/**
 * Wrapper Class for Google Recaptcha
 * Used to fix dynamic property deprecation error
 */
class ReCaptcha extends GoogleReCaptcha
{

    /**
     * @var float
     */
    protected float $threshold = 0.0;
}

<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\ReCaptchaFrontendUi\Plugin;

use Magento\Framework\View\Asset\Minification;

/**
 * Exclude external recaptcha from minification
 */
class ExcludeFromMinification
{
    /**
     * Exclude external recaptcha from minification
     *
     * @param Minification $subject
     * @param callable $proceed
     * @param string $contentType
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetExcludes(Minification $subject, callable $proceed, $contentType)
    {
        $result = $proceed($contentType);
        if ($contentType !== 'js') {
            return $result;
        }
        $result[] = 'https://www.google.com/recaptcha/api.js';
        return $result;
    }
}

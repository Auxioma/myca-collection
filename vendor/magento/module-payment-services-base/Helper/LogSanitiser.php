<?php

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace Magento\PaymentServicesBase\Helper;

class LogSanitiser
{
    /**
     * Strip HTML tags, encode residual angle brackets, remove control characters, and truncate.
     *
     * strip_tags() alone does not encode angle brackets that are not part of a recognised tag
     * (e.g. "a < b" survives verbatim), so we HTML-encode residual "<" and ">" after tag stripping.
     * Control characters (\x00-\x1F, \x7F) are removed to prevent log injection.
     * Deeper Unicode sanitization (bidi controls, format chars) is handled by the backend.
     *
     * @param string $value
     * @param int $maxLength
     * @return string
     */
    public function sanitize(string $value, int $maxLength): string
    {
        $value = strip_tags($value);
        $value = str_replace(['<', '>'], ['&lt;', '&gt;'], $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        return mb_substr($value, 0, $maxLength);
    }
}

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

namespace Magento\PaymentServicesBase\Test\Unit\Helper;

use Magento\PaymentServicesBase\Helper\LogSanitiser;
use PHPUnit\Framework\TestCase;

class LogSanitiserTest extends TestCase
{
    private LogSanitiser $logSanitiser;

    protected function setUp(): void
    {
        $this->logSanitiser = new LogSanitiser();
    }

    public function testStripsHtmlTags(): void
    {
        $this->assertSame('payload', $this->logSanitiser->sanitize('<img src=x onerror=alert(1)>payload', 2048));
    }

    public function testEncodesResidualAngleBrackets(): void
    {
        $this->assertSame('a &lt; b', $this->logSanitiser->sanitize('a < b', 2048));
    }

    public function testStripsControlCharacters(): void
    {
        $this->assertSame('injectedmessage', $this->logSanitiser->sanitize("injected\x0Amessage", 2048));
    }

    public function testStripsNullByte(): void
    {
        $this->assertSame('message', $this->logSanitiser->sanitize("\x00message", 2048));
    }

    public function testStripsDeleteCharacter(): void
    {
        $this->assertSame('message', $this->logSanitiser->sanitize("message\x7F", 2048));
    }

    public function testTruncatesToMaxLength(): void
    {
        $result = $this->logSanitiser->sanitize(str_repeat('a', 3000), 2048);
        $this->assertSame(2048, mb_strlen($result));
    }

    public function testPreservesUrlsInMessages(): void
    {
        $message = 'SDK_LOADING_FAILURE: The script "https://commerce-payments-sdk.int.adobe.io/v0/6/PaymentSDK.js" failed to load.';
        $this->assertSame($message, $this->logSanitiser->sanitize($message, 2048));
    }

    public function testPreservesPaymentLocationPrefix(): void
    {
        $message = '[CHECKOUT] SDK_LOADING_FAILURE: Script failed to load.';
        $this->assertSame($message, $this->logSanitiser->sanitize($message, 2048));
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', $this->logSanitiser->sanitize('', 2048));
    }

    public function testAllControlCharsReturnEmpty(): void
    {
        $this->assertSame('', $this->logSanitiser->sanitize("\x00\x01\x1F\x7F", 2048));
    }
}

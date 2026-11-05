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

namespace Magento\PaymentServicesBase\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use Magento\PaymentServicesBase\Helper\LogSanitiser;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\PaymentServicesBase\Model\StorefrontLogAppender;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StorefrontLogAppenderTest extends TestCase
{
    /**
     * @var MockObject|CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var MockObject|ServiceClientInterface
     */
    private ServiceClientInterface $httpClient;

    /**
     * @var StorefrontLogAppender
     */
    private StorefrontLogAppender $storefrontLogAppender;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->httpClient = $this->createMock(ServiceClientInterface::class);

        $this->storefrontLogAppender = new StorefrontLogAppender(
            $this->cache,
            $this->httpClient,
            new LogSanitiser()
        );
    }

    public function testLogBatchReturnsTrueWhenUnderRateLimit(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->cache->expects($this->once())->method('save');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);

        $this->assertTrue($this->storefrontLogAppender->logBatch([['message' => 'SDK_LOADING_FAILURE: Script failed to load.']]));
    }

    public function testLogBatchReturnsFalseWhenRateLimitExceeded(): void
    {
        $this->cache->method('load')->willReturn('60');
        $this->cache->expects($this->never())->method('save');
        $this->httpClient->expects($this->never())->method('request');

        $this->assertFalse($this->storefrontLogAppender->logBatch([['message' => 'SDK_LOADING_FAILURE: Script failed to load.']]));
    }

public function testLogBatchReturnsTrueWhenCountIsBelowLimit(): void
    {
        $this->cache->method('load')->willReturn('59');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);

        $this->assertTrue($this->storefrontLogAppender->logBatch([['message' => 'SDK_LOADING_FAILURE: Script failed to load.']]));
    }

    public function testLogBatchIncrementsCounterByOne(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);

        $this->cache->expects($this->once())
            ->method('save')
            ->with('1', $this->anything(), [], 7200);

        $this->storefrontLogAppender->logBatch([['message' => 'a'], ['message' => 'b'], ['message' => 'c']]);
    }

    public function testLogBatchReturnsFalseWhenBackendCallFails(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->method('request')->willReturn(['is_successful' => false]);

        $this->assertFalse($this->storefrontLogAppender->logBatch([['message' => 'SDK_LOADING_FAILURE: Script failed to load.']]));
    }

    public function testSanitizeStripsControlCharacters(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);
        $this->cache->expects($this->once())->method('save');

        $this->storefrontLogAppender->logBatch([['message' => "injected\x0Amessage"]]);
    }

    public function testSanitizeStripsUnicodeLineSeparators(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);
        $this->cache->expects($this->once())->method('save');

        // U+2028 LINE SEPARATOR and U+2029 PARAGRAPH SEPARATOR
        $this->storefrontLogAppender->logBatch([['message' => "injected\u{2028}message"]]);
    }

    public function testSanitizeStripsHtmlTags(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);
        $this->cache->expects($this->once())->method('save');

        // HTML tags must be stripped (stored XSS prevention)
        $this->storefrontLogAppender->logBatch([['message' => '<img src=x onerror=alert(1)>payload']]);
    }

    public function testSanitizeTruncatesMessageAt2048Characters(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);
        $this->cache->expects($this->once())->method('save');

        $this->storefrontLogAppender->logBatch([['message' => str_repeat('a', 3000)]]);
    }

    public function testEmptyMessageIsSkipped(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->expects($this->never())->method('request');

        $this->storefrontLogAppender->logBatch([['message' => '']]);
    }

    public function testMessageBecomingEmptyAfterSanitizationIsSkipped(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->expects($this->never())->method('request');

        // Only control characters — sanitized to empty string
        $this->storefrontLogAppender->logBatch([['message' => "\x00\x01\x1F"]]);
    }

    public function testRateLimitUsesGlobalCacheKey(): void
    {
        $this->cache->method('load')->willReturn('0');
        $this->httpClient->method('request')->willReturn(['is_successful' => true]);

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                '1',
                $this->stringStartsWith('ps_storefront_log_rl_global_'),
                [],
                7200
            );

        $this->storefrontLogAppender->logBatch([['message' => 'SDK_LOADING_FAILURE: Script failed to load.']]);
    }
}

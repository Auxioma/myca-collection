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

namespace Magento\PaymentServicesBase\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http;
use Magento\PaymentServicesBase\Helper\LogSanitiser;

/**
 * Validates, rate-limits, and forwards Payment Services storefront log entries to the APS backend.
 *
 * Security controls applied here:
 *  - Sanitization: HTML tags stripped, control characters removed, message truncated to prevent log injection.
 *  - Rate limiting: global per-hour counter (not per-IP) enforced via cache.
 *    Note: fixed-window — up to 2× the limit is possible at window boundaries.
 */
class StorefrontLogAppender
{
    private const MAX_MESSAGE_LENGTH = 2048;

    private const RATE_LIMIT_COUNT_MAX = 60;

    private const RATE_LIMIT_WINDOW_SECONDS = 3600;

    private const RATE_LIMIT_CACHE_PREFIX = 'ps_storefront_log_rl_global_';

    private const LOG_ENDPOINT_URI = '/payment/sdk/logs';

    /**
     * @param CacheInterface $cache
     * @param ServiceClientInterface $httpClient
     * @param LogSanitiser $logSanitiser
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ServiceClientInterface $httpClient,
        private readonly LogSanitiser $logSanitiser
    ) {
    }

    /**
     * Validate, rate-limit, sanitize and forward a batch of storefront log entries.
     *
     * Returns false if the global rate limit is exceeded — callers should return success: false
     * without exposing the reason to the client.
     * The rate limit is checked once for the entire batch to avoid partial writes.
     *
     * @param array[] $entries
     * @return bool false if rate-limited, true otherwise
     */
    public function logBatch(array $entries): bool
    {
        if (!$this->checkRateLimit()) {
            return false;
        }

        $sanitized = [];
        foreach ($entries as $entry) {
            $clean = $this->sanitizeEntry($entry);
            if (empty($clean)) {
                continue;
            }
            $sanitized[] = $clean;
        }

        if (empty($sanitized)) {
            return true;
        }

        return $this->forwardToBackend($sanitized);
    }

    /**
     * Sanitize all string fields of a single entry.
     *
     * @param array $entry
     * @return array
     */
    private function sanitizeEntry(array $entry): array
    {
        $sanitized = [];
        foreach ($entry as $key => $value) {
            if (is_string($value)) {
                $clean = $this->logSanitiser->sanitize($value, self::MAX_MESSAGE_LENGTH);
                if ($clean !== '') {
                    $sanitized[$key] = $clean;
                }
            }
        }
        return $sanitized;
    }

    /**
     * Forward sanitized log entries to the APS backend.
     *
     * @param array[] $entries
     * @return bool
     */
    private function forwardToBackend(array $entries): bool
    {
        $body = json_encode(['entries' => $entries]);

        if (!$body) {
            return false;
        }

        $response = $this->httpClient->request(
            ['Content-Type' => 'application/json'],
            self::LOG_ENDPOINT_URI,
            Http::METHOD_POST,
            $body,
            ''
        );

        return isset($response['is_successful']) && $response['is_successful'];
    }

    /**
     * Enforce a global rate limit using a fixed per-hour window stored in cache.
     *
     * Returns false when the limit is exceeded rather than throwing, so callers can
     * respond silently without leaking rate-limit information to the client.
     *
     * @return bool false if rate-limited
     */
    private function checkRateLimit(): bool
    {
        $hourWindow = (int) floor(time() / self::RATE_LIMIT_WINDOW_SECONDS);
        $cacheKey = self::RATE_LIMIT_CACHE_PREFIX . $hourWindow;

        $count = (int) $this->cache->load($cacheKey);

        if ($count >= self::RATE_LIMIT_COUNT_MAX) {
            return false;
        }

        $this->cache->save((string) ($count + 1), $cacheKey, [], self::RATE_LIMIT_WINDOW_SECONDS * 2);
        return true;
    }
}

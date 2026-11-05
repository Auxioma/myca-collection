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

namespace Magento\PaymentServicesPaypalGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\StorefrontLogAppender;

/**
 * Resolver for the appendPaymentServicesStorefrontLogs GraphQL mutation.
 *
 * Accepts SDK events reported by the browser, validates and sanitizes them, then forwards
 * them to the APS backend for centralized monitoring in Splunk.
 */
class AppendPaymentServicesStorefrontLogs implements ResolverInterface
{
    private const MAX_ENTRIES = 10;

    /**
     * @param StorefrontLogAppender $storefrontLogAppender
     * @param Config $config
     */
    public function __construct(
        private readonly StorefrontLogAppender $storefrontLogAppender,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!$this->config->isEnabled() || !$this->config->isStorefrontLoggerEnabled()) {
            return ['success' => false];
        }

        $entries = $args['logs']['entries'] ?? [];

        if (count($entries) > self::MAX_ENTRIES) {
            throw new GraphQlInputException(__('Maximum %1 log entries per request.', self::MAX_ENTRIES));
        }

        $success = $this->storefrontLogAppender->logBatch($entries);

        return ['success' => $success];
    }
}

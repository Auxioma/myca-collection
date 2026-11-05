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

namespace Magento\SaaSCommon\Model\Indexer;

use Magento\DataExporter\Model\Indexer\FeedReadinessCheckerInterface;
use Magento\ServicesId\Model\ServicesConfigInterface;

/**
 * Reports a feed as not ready when Services Connector credentials are not configured,
 * so indexation is skipped instead of repeatedly failing to submit to SaaS.
 */
class ServicesConnectorReadinessChecker implements FeedReadinessCheckerInterface
{
    /**
     * @param ServicesConfigInterface $servicesConfig
     */
    public function __construct(private readonly ServicesConfigInterface $servicesConfig)
    {
    }

    /**
     * @inheritDoc
     */
    public function isReady(): bool
    {
        return $this->servicesConfig->isApiKeySet();
    }
}

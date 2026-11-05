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

namespace Magento\PaymentServicesPaypal\Model\Metadata;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;

/**
 * Sends merchant metadata to the Payment Services configuration service.
 */
class MetadataService
{
    private const METADATA_PATH = '/admin/metadata';
    private const RESPONSE_TYPE_RAW = 'raw';

    /**
     * @param ServiceClientInterface $serviceClient
     * @param Json $serializer
     */
    public function __construct(
        private readonly ServiceClientInterface $serviceClient,
        private readonly Json $serializer
    ) {
    }

    /**
     * Upsert metadata for an environment.
     *
     * @param array $metadata
     * @param string $environment
     */
    public function upsert(array $metadata, string $environment): bool
    {
        $response = $this->serviceClient->request(
            ['Content-Type' => 'application/json'],
            self::METADATA_PATH,
            Http::METHOD_PUT,
            $this->serializer->serialize(['metadata' => $metadata]),
            self::RESPONSE_TYPE_RAW,
            $environment
        );

        return ($response['is_successful'] ?? false) === true;
    }
}

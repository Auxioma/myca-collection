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

use Magento\Framework\FlagManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;

/**
 * Synchronizes changed merchant metadata after verifying onboarding.
 */
class Synchronizer
{
    private const DIGEST_VERSION = 'v1';
    private const FLAG_PREFIX = 'payment_services_metadata_digest_';
    private const SUPPORTED_SCOPE_TYPES = [
        PaypalMerchantResolver::GLOBAL_SCOPE,
        ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
        ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE,
    ];

    /**
     * @param MetadataProvider $metadataProvider
     * @param MetadataService $metadataService
     * @param MerchantService $merchantService
     * @param PaypalMerchantResolver $paypalMerchantResolver
     * @param Config $config
     * @param FlagManager $flagManager
     * @param Json $serializer
     */
    public function __construct(
        private readonly MetadataProvider $metadataProvider,
        private readonly MetadataService $metadataService,
        private readonly MerchantService $merchantService,
        private readonly PaypalMerchantResolver $paypalMerchantResolver,
        private readonly Config $config,
        private readonly FlagManager $flagManager,
        private readonly Json $serializer
    ) {
    }

    /**
     * Synchronize changed metadata for every selected environment.
     */
    public function synchronize(): void
    {
        foreach ($this->metadataProvider->getMetadataByEnvironment() as $environment => $metadata) {
            $this->synchronizeEnvironment($environment, $metadata);
        }
    }

    /**
     * Synchronize one environment when its payload has changed.
     *
     * @param string $environment
     * @param array $metadata
     */
    private function synchronizeEnvironment(string $environment, array $metadata): void
    {
        $merchantId = trim($this->config->getMerchantId($environment));
        if ($merchantId === '' || !$this->config->isServicesConnectorConfigured($environment)) {
            return;
        }

        $digest = hash('sha256', implode("\0", [
            self::DIGEST_VERSION,
            $merchantId,
            $this->serializer->serialize($metadata),
        ]));
        $flagName = self::FLAG_PREFIX . $environment;
        $previousDigest = (string) $this->flagManager->getFlagData($flagName);
        if ($previousDigest !== '' && hash_equals($previousDigest, $digest)) {
            return;
        }

        $scopes = $this->merchantService->getAllScopesForMerchant($environment, true);
        if (!$this->hasCompletedOnboarding($scopes)) {
            return;
        }

        if ($this->metadataService->upsert($metadata, $environment)) {
            $this->flagManager->saveFlag($flagName, $digest);
        }
    }

    /**
     * Check for a completed PayPal account at a supported scope.
     *
     * @param array $scopes
     */
    private function hasCompletedOnboarding(array $scopes): bool
    {
        $validScopes = array_values(array_filter($scopes, static function ($scope): bool {
            return is_array($scope)
                && isset($scope['scopeType'], $scope['scopeId'], $scope['paypal-account'])
                && is_array($scope['paypal-account'])
                && in_array(strtolower((string) $scope['scopeType']), self::SUPPORTED_SCOPE_TYPES, true)
                && is_numeric($scope['scopeId']);
        }));

        foreach ($validScopes as $scope) {
            $paypalMerchant = $this->paypalMerchantResolver->getPaypalMerchantForExactScope(
                $validScopes,
                (int) $scope['scopeId'],
                strtolower((string) $scope['scopeType'])
            );
            if ($paypalMerchant !== null
                && trim((string) $paypalMerchant->getId()) !== ''
                && $paypalMerchant->getStatus() === PaypalMerchantInterface::COMPLETED_STATUS
            ) {
                return true;
            }
        }

        return false;
    }
}

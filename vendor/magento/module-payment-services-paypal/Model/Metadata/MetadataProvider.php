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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Provides Commerce metadata grouped by environment.
 */
class MetadataProvider
{
    private const SECURE_BASE_URL_PATH = 'web/secure/base_url';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param PaypalMerchantResolver $paypalMerchantResolver
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly PaypalMerchantResolver $paypalMerchantResolver
    ) {
    }

    /**
     * Get metadata grouped by the environment selected at each scope.
     *
     * @return array
     */
    public function getMetadataByEnvironment(): array
    {
        $metadata = [];
        $this->createWebsiteUrlMetadata($metadata);

        return $metadata;
    }

    /**
     * Add secure base URL metadata that differs from its parent scope.
     *
     * @param array $metadata
     */
    private function createWebsiteUrlMetadata(array &$metadata): void
    {
        $globalConfig = $this->getScopeConfig(
            PaypalMerchantResolver::GLOBAL_SCOPE,
            0,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
        $this->addMetadata($metadata, $globalConfig, null, 'GLOBAL', 0);

        $websiteConfigs = [];
        $websites = array_values($this->storeManager->getWebsites());
        usort(
            $websites,
            static fn (WebsiteInterface $left, WebsiteInterface $right): int =>
                (int) $left->getId() <=> (int) $right->getId()
        );
        foreach ($websites as $website) {
            $websiteId = (int) $website->getId();
            $websiteConfigs[$websiteId] = $this->getScopeConfig(
                ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
                $websiteId,
                ScopeInterface::SCOPE_WEBSITES
            );
            $this->addMetadata(
                $metadata,
                $websiteConfigs[$websiteId],
                $globalConfig,
                'WEBSITE',
                $websiteId
            );
        }

        $stores = array_values($this->storeManager->getStores());
        usort(
            $stores,
            static fn (StoreInterface $left, StoreInterface $right): int =>
                (int) $left->getId() <=> (int) $right->getId()
        );
        foreach ($stores as $store) {
            $storeId = (int) $store->getId();
            $storeConfig = $this->getScopeConfig(
                ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE,
                $storeId,
                ScopeInterface::SCOPE_STORES
            );
            $parentConfig = $websiteConfigs[(int) $store->getWebsiteId()] ?? $globalConfig;
            $this->addMetadata($metadata, $storeConfig, $parentConfig, 'STOREVIEW', $storeId);
        }
    }

    /**
     * Resolve the URL and environment at a scope.
     *
     * @param string $resolverScopeType
     * @param int $scopeId
     * @param string $configScopeType
     */
    private function getScopeConfig(
        string $resolverScopeType,
        int $scopeId,
        string $configScopeType
    ): array {
        $url = rtrim(trim((string) $this->scopeConfig->getValue(
            self::SECURE_BASE_URL_PATH,
            $configScopeType,
            $scopeId
        )), '/');

        return [
            'url' => $url,
            'environment' => $url === ''
                ? ''
                : $this->paypalMerchantResolver->resolveEnvironment($resolverScopeType, $scopeId),
        ];
    }

    /**
     * Add a nonblank, supported scope configuration when it differs from its parent.
     *
     * @param array $metadata
     * @param array $scopeConfig
     * @param array|null $parentConfig
     * @param string $scopeType
     * @param int $scopeId
     */
    private function addMetadata(
        array &$metadata,
        array $scopeConfig,
        ?array $parentConfig,
        string $scopeType,
        int $scopeId
    ): void {
        if ($scopeConfig === $parentConfig
            || $scopeConfig['url'] === ''
            || !in_array(
                $scopeConfig['environment'],
                [Config::SANDBOX_ENVIRONMENT, Config::PRODUCTION_ENVIRONMENT],
                true
            )
        ) {
            return;
        }

        $metadata[$scopeConfig['environment']][] = [
            'key' => self::SECURE_BASE_URL_PATH,
            'value' => $scopeConfig['url'],
            'scopeType' => $scopeType,
            'scopeId' => (string) $scopeId,
        ];
    }
}

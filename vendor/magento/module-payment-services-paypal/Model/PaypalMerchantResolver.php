<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;

class PaypalMerchantResolver
{
    public const GLOBAL_SCOPE = "global";

    private const MAP_MBA_SCOPE_TO_SAAS_CONFIG = [
        ScopeInterface::SCOPE_WEBSITE => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
        ScopeInterface::SCOPE_STORE => ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE,
    ];

    /**
     * @param Config $config
     * @param MerchantService $merchantService
     * @param StoreManager $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly MerchantService $merchantService,
        private readonly StoreManager $storeManager
    ) {
    }

    /**
     * Get PayPal merchant for the given scope id
     *
     * @param "global"|"website"|"storeview" $scopeType
     * @param int $scopeId
     * @return PaypalMerchantInterface
     */
    public function getPayPalMerchant(
        string $scopeType,
        int $scopeId
    ): PaypalMerchantInterface {
        $mbaScoping = self::MAP_MBA_SCOPE_TO_SAAS_CONFIG[$this->config->getMultiBusinessAccountScopingLevel()];
        $environment = $this->resolveEnvironment($scopeType, $scopeId);
        $scopes = $this->getScopesFilteredByMbaScopingLevel($mbaScoping, $environment);
        $globalPaypalMerchant = $this->getGlobalScopePaypalMerchant($scopes);

        // If we show configuration on global scope, return the global scope merchant directly
        if ($this->isGlobalScope($scopeType)) {
            return $globalPaypalMerchant;
        }

        // If mba scoping level is website and the scopeType is STOREVIEW, we need to check at the website level
        if ($this->isMbaScopingAtWebsiteLevel($mbaScoping) && $this->isStoreViewScope($scopeType)) {
            return $this->resolvePayPalMerchantForStoreView($scopeId, $scopes) ?? $globalPaypalMerchant;
        }

        // Otherwise, check the exact scope because:
        // - Mba scoping level is website and the scopeType is WEBSITE
        // - or mba scoping level is storeview and the scopeType is STOREVIEW
        $paypalMerchant = $this->getPaypalMerchantForExactScope($scopes, $scopeId, $scopeType);

        return $paypalMerchant ?? $globalPaypalMerchant;
    }

    /**
     * Get PayPal merchant for an exact scope without falling back to global scope.
     *
     * @param "website" $scopeType
     * @param int $scopeId
     * @param string|null $environment
     * @return PaypalMerchantInterface|null
     */
    public function getExactPayPalMerchant(
        string $scopeType,
        int $scopeId,
        string $environment
    ): ?PaypalMerchantInterface {
        return $this->getPaypalMerchantForExactScope(
            $this->merchantService->getAllScopesForMerchant($environment),
            $scopeId,
            $scopeType
        );
    }

    /**
     * Get merchant scopes from cache or api request filtered by the MBA scoping level
     *
     * @param string $mbaScoping
     * @param string|null $environment
     * @return array
     */
    private function getScopesFilteredByMbaScopingLevel(
        string $mbaScoping,
        string $environment
    ): array {
        $scopes = $this->merchantService->getAllScopesForMerchant($environment);

        return array_filter($scopes, function ($scope) use ($mbaScoping) {
            return !empty($scope['scopeType'])
                && (strcasecmp($scope['scopeType'], $mbaScoping) === 0)
                    || $this->isGlobalScope($scope['scopeType']);
        });
    }

    /**
     * Get the PayPal merchant for the global scope
     *  ['paypal-account']['id'] can be null but not ['paypal-account']['status']
     *  so PaypalMerchantData cannot be fully null
     *
     * @param array $scopes
     * @return PaypalMerchantInterface
     */
    private function getGlobalScopePaypalMerchant(array $scopes): PaypalMerchantInterface
    {
        $globalScope =  array_filter($scopes, function ($scope) {
            return $this->isGlobalScope($scope['scopeType']);
        });

        $globalScope = array_shift($globalScope) ?? [];

        return new PaypalMerchantData(
            $globalScope['paypal-account']['id'] ?? null,
            $globalScope['paypal-account']['status'] ?? null
        );
    }

    /**
     * Resolves the PayPal Merchant for the store view by checking at the website level
     *
     * @param int $scopeId
     * @param array $scopes
     * @return ?PaypalMerchantInterface
     */
    private function resolvePayPalMerchantForStoreView(
        int $scopeId,
        array $scopes
    ): ?PaypalMerchantInterface {
        $websiteId = $this->getWebsiteIdByStore($scopeId);

        return $this->getPaypalMerchantForExactScope(
            $scopes,
            $websiteId,
            ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE
        );
    }

    /**
     * Get the PayPal merchant for the exact scope
     *
     * @param array $scopes
     * @param int $scopeId
     * @param string $scopeType
     * @return ?PaypalMerchantInterface
     */
    public function getPaypalMerchantForExactScope(
        array $scopes,
        int $scopeId,
        string $scopeType
    ): ?PaypalMerchantInterface {
        foreach ($scopes as $scope) {
            if ($scope['scopeId'] == $scopeId && strcasecmp($scope['scopeType'], $scopeType) === 0) {
                return new PaypalMerchantData(
                    $scope['paypal-account']['id'] ?? null,
                    $scope['paypal-account']['status'] ?? null
                );
            }
        }

        return null;
    }

    /**
     * Is global scope
     *
     * @param string $scopeType
     * @return bool
     */
    private function isGlobalScope(string $scopeType): bool
    {
        return strcasecmp($scopeType, self::GLOBAL_SCOPE) === 0;
    }

    /**
     * Is Website Scoping for mba configuration
     *
     * @param string $mbaScoping
     * @return bool
     */
    private function isMbaScopingAtWebsiteLevel(string $mbaScoping): bool
    {
        return strcasecmp($mbaScoping, ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE) === 0;
    }

    /**
     * Is Store View Scope
     *
     * @param string $scopeType
     * @return bool
     */
    private function isStoreViewScope(string $scopeType): bool
    {
        return strcasecmp($scopeType, ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE) === 0;
    }

    /**
     * Get the website id of the current storeview scope or return 0 if no website to default to GLOBAL
     *
     * @param int $storeId
     * @return int
     */
    private function getWebsiteIdByStore(int $storeId): int
    {
        try {
            return (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        } catch (NoSuchEntityException $e) {
            return 0;
        }
    }

    /**
     * Resolve environment based on selected scope
     *
     * @param string $scopeType
     * @param int $scopeId
     * @return string
     */
    public function resolveEnvironment(
        string $scopeType,
        int $scopeId
    ): string {
        if ($this->isGlobalScope($scopeType)) {
            return $this->config->getEnvironmentType();
        }

        if ($this->isStoreViewScope($scopeType)) {
            return $this->config->getEnvironmentType($scopeId);
        }

        return $this->config->getEnvironmentTypeForWebsite($scopeId);
    }
}

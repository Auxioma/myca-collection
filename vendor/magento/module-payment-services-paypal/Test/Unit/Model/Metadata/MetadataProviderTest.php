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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\Metadata;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\Metadata\MetadataProvider;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MetadataProviderTest extends TestCase
{
    /** @var ScopeConfigInterface|MockObject */
    private ScopeConfigInterface|MockObject $scopeConfig;

    /** @var StoreManagerInterface|MockObject */
    private StoreManagerInterface|MockObject $storeManager;

    /** @var PaypalMerchantResolver|MockObject */
    private PaypalMerchantResolver|MockObject $paypalMerchantResolver;

    /** @var MetadataProvider */
    private MetadataProvider $provider;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->paypalMerchantResolver = $this->createMock(PaypalMerchantResolver::class);
        $this->provider = new MetadataProvider(
            $this->scopeConfig,
            $this->storeManager,
            $this->paypalMerchantResolver
        );
    }

    public function testReturnsOnlyGlobalWhenAllScopesInheritTheSameConfiguration(): void
    {
        $this->storeManager->method('getWebsites')->willReturn([
            $this->createWebsite(2),
            $this->createWebsite(1),
        ]);
        $this->storeManager->method('getStores')->willReturn([
            $this->createStore(4, 2),
            $this->createStore(3, 1),
        ]);
        $this->scopeConfig->method('getValue')->willReturn('https://example.com/');
        $this->paypalMerchantResolver->method('resolveEnvironment')->willReturn('sandbox');

        $this->assertSame([
            'sandbox' => [$this->entry('https://example.com', 'GLOBAL', 0)],
        ], $this->provider->getMetadataByEnvironment());
    }

    public function testReturnsOnlyUrlDifferencesInDeterministicScopeOrder(): void
    {
        $this->storeManager->method('getWebsites')->willReturn([
            $this->createWebsite(2),
            $this->createWebsite(1),
        ]);
        $this->storeManager->method('getStores')->willReturn([
            $this->createStore(5, 2),
            $this->createStore(4, 1),
            $this->createStore(3, 1),
        ]);
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['web/secure/base_url', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0, 'https://global.example.com/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_WEBSITES, 1, 'https://one.example.com/path/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_WEBSITES, 2, 'https://global.example.com/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_STORES, 3, 'https://one.example.com/path/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_STORES, 4, 'https://four.example.com/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_STORES, 5, 'https://global.example.com/'],
        ]);
        $this->paypalMerchantResolver->method('resolveEnvironment')->willReturn('sandbox');

        $this->assertSame([
            'sandbox' => [
                $this->entry('https://global.example.com', 'GLOBAL', 0),
                $this->entry('https://one.example.com/path', 'WEBSITE', 1),
                $this->entry('https://four.example.com', 'STOREVIEW', 4),
            ],
        ], $this->provider->getMetadataByEnvironment());
    }

    public function testSameUrlInDifferentEnvironmentIsNotConsideredDuplicate(): void
    {
        $this->storeManager->method('getWebsites')->willReturn([$this->createWebsite(1)]);
        $this->storeManager->method('getStores')->willReturn([$this->createStore(2, 1)]);
        $this->scopeConfig->method('getValue')->willReturn('https://example.com/');
        $this->paypalMerchantResolver->method('resolveEnvironment')->willReturnMap([
            [PaypalMerchantResolver::GLOBAL_SCOPE, 0, 'sandbox'],
            [ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE, 1, 'production'],
            [ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE, 2, 'production'],
        ]);

        $this->assertSame([
            'sandbox' => [$this->entry('https://example.com', 'GLOBAL', 0)],
            'production' => [$this->entry('https://example.com', 'WEBSITE', 1)],
        ], $this->provider->getMetadataByEnvironment());
    }

    public function testStoreViewIsComparedWithItsWebsiteInsteadOfGlobal(): void
    {
        $this->storeManager->method('getWebsites')->willReturn([$this->createWebsite(1)]);
        $this->storeManager->method('getStores')->willReturn([$this->createStore(2, 1)]);
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['web/secure/base_url', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0, 'https://global.example.com/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_WEBSITES, 1, 'https://website.example.com/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_STORES, 2, 'https://global.example.com/'],
        ]);
        $this->paypalMerchantResolver->method('resolveEnvironment')->willReturn('sandbox');

        $this->assertSame([
            'sandbox' => [
                $this->entry('https://global.example.com', 'GLOBAL', 0),
                $this->entry('https://website.example.com', 'WEBSITE', 1),
                $this->entry('https://global.example.com', 'STOREVIEW', 2),
            ],
        ], $this->provider->getMetadataByEnvironment());
    }

    public function testOmitsBlankValuesAndUnsupportedEnvironments(): void
    {
        $this->storeManager->method('getWebsites')->willReturn([$this->createWebsite(1)]);
        $this->storeManager->method('getStores')->willReturn([$this->createStore(2, 1)]);
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['web/secure/base_url', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0, '   '],
            ['web/secure/base_url', ScopeInterface::SCOPE_WEBSITES, 1, 'ftp://commerce.example.com/'],
            ['web/secure/base_url', ScopeInterface::SCOPE_STORES, 2, 'https://store.example.com/'],
        ]);
        $this->paypalMerchantResolver->method('resolveEnvironment')->willReturnMap([
            [ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE, 1, 'sandbox'],
            [ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE, 2, 'unsupported'],
        ]);

        $this->assertSame([
            'sandbox' => [$this->entry('ftp://commerce.example.com', 'WEBSITE', 1)],
        ], $this->provider->getMetadataByEnvironment());
    }

    private function createWebsite(int $id): WebsiteInterface
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn($id);
        return $website;
    }

    private function createStore(int $id, int $websiteId): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getWebsiteId')->willReturn($websiteId);
        return $store;
    }

    private function entry(string $value, string $scopeType, int $scopeId): array
    {
        return [
            'key' => 'web/secure/base_url',
            'value' => $value,
            'scopeType' => $scopeType,
            'scopeId' => (string) $scopeId,
        ];
    }
}

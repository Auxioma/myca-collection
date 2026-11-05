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

use Magento\Framework\FlagManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesPaypal\Model\Metadata\MetadataProvider;
use Magento\PaymentServicesPaypal\Model\Metadata\MetadataService;
use Magento\PaymentServicesPaypal\Model\Metadata\Synchronizer;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SynchronizerTest extends TestCase
{
    private const METADATA = [[
        'key' => 'web/secure/base_url',
        'value' => 'https://example.com',
        'scopeType' => 'GLOBAL',
        'scopeId' => '0',
    ]];

    /** @var MetadataProvider|MockObject */
    private MetadataProvider|MockObject $metadataProvider;

    /** @var MetadataService|MockObject */
    private MetadataService|MockObject $metadataService;

    /** @var MerchantService|MockObject */
    private MerchantService|MockObject $merchantService;

    /** @var PaypalMerchantResolver|MockObject */
    private PaypalMerchantResolver|MockObject $paypalMerchantResolver;

    /** @var Config|MockObject */
    private Config|MockObject $config;

    /** @var FlagManager|MockObject */
    private FlagManager|MockObject $flagManager;

    /** @var Json|MockObject */
    private Json|MockObject $serializer;

    /** @var Synchronizer */
    private Synchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->metadataProvider = $this->createMock(MetadataProvider::class);
        $this->metadataService = $this->createMock(MetadataService::class);
        $this->merchantService = $this->createMock(MerchantService::class);
        $this->paypalMerchantResolver = $this->createMock(PaypalMerchantResolver::class);
        $this->config = $this->createMock(Config::class);
        $this->flagManager = $this->createMock(FlagManager::class);
        $this->serializer = $this->createMock(Json::class);
        $this->synchronizer = new Synchronizer(
            $this->metadataProvider,
            $this->metadataService,
            $this->merchantService,
            $this->paypalMerchantResolver,
            $this->config,
            $this->flagManager,
            $this->serializer
        );
    }

    public function testUnchangedMetadataMakesNoSaasCalls(): void
    {
        $this->metadataProvider->method('getMetadataByEnvironment')->willReturn([
            'sandbox' => self::METADATA,
        ]);
        $this->config->method('getMerchantId')->with('sandbox')->willReturn('merchant-id');
        $this->config->method('isServicesConnectorConfigured')->with('sandbox')->willReturn(true);
        $this->serializer->method('serialize')->with(self::METADATA)->willReturn('serialized');
        $digest = hash('sha256', implode("\0", ['v1', 'merchant-id', 'serialized']));
        $this->flagManager->method('getFlagData')
            ->with('payment_services_metadata_digest_sandbox')
            ->willReturn($digest);
        $this->merchantService->expects($this->never())->method('getAllScopesForMerchant');
        $this->metadataService->expects($this->never())->method('upsert');
        $this->flagManager->expects($this->never())->method('saveFlag');

        $this->synchronizer->synchronize();
    }

    /**
     * @dataProvider completedScopeProvider
     */
    public function testCompletedOnboardingAtAnySupportedScopeAllowsPut(
        string $scopeType,
        int $scopeId
    ): void {
        $scopes = [[
            'scopeType' => $scopeType,
            'scopeId' => $scopeId,
            'paypal-account' => ['id' => 'paypal-id', 'status' => 'COMPLETED'],
        ]];
        $this->prepareChangedSandboxGroup('merchant-id', 'serialized', $scopes);
        $this->paypalMerchantResolver->expects($this->once())
            ->method('getPaypalMerchantForExactScope')
            ->with($scopes, $scopeId, strtolower($scopeType))
            ->willReturn($this->createPaypalMerchant('paypal-id', PaypalMerchantInterface::COMPLETED_STATUS));
        $this->metadataService->expects($this->once())
            ->method('upsert')
            ->with(self::METADATA, 'sandbox')
            ->willReturn(true);
        $this->flagManager->expects($this->once())
            ->method('saveFlag')
            ->with(
                'payment_services_metadata_digest_sandbox',
                hash('sha256', implode("\0", ['v1', 'merchant-id', 'serialized']))
            );

        $this->synchronizer->synchronize();
    }

    public static function completedScopeProvider(): array
    {
        return [
            'global' => ['GLOBAL', 0],
            'website' => ['WEBSITE', 1],
            'store view' => ['STOREVIEW', 2],
        ];
    }

    public function testIncompleteOnboardingDoesNotPutOrSaveState(): void
    {
        $scopes = [[
            'scopeType' => 'WEBSITE',
            'scopeId' => 1,
            'paypal-account' => ['id' => 'paypal-id', 'status' => 'STARTED'],
        ]];
        $this->prepareChangedSandboxGroup('merchant-id', 'serialized', $scopes);
        $this->paypalMerchantResolver->method('getPaypalMerchantForExactScope')
            ->willReturn($this->createPaypalMerchant('paypal-id', PaypalMerchantInterface::STARTED_STATUS));
        $this->metadataService->expects($this->never())->method('upsert');
        $this->flagManager->expects($this->never())->method('saveFlag');

        $this->synchronizer->synchronize();
    }

    public function testMalformedOnboardingResponseDoesNotPut(): void
    {
        $this->prepareChangedSandboxGroup('merchant-id', 'serialized', [
            ['scopeType' => 'WEBSITE', 'scopeId' => 1],
            ['unexpected' => 'value'],
        ]);
        $this->paypalMerchantResolver->expects($this->never())->method('getPaypalMerchantForExactScope');
        $this->metadataService->expects($this->never())->method('upsert');

        $this->synchronizer->synchronize();
    }

    public function testFailedPutDoesNotSaveState(): void
    {
        $scopes = [[
            'scopeType' => 'GLOBAL',
            'scopeId' => 0,
            'paypal-account' => ['id' => 'paypal-id', 'status' => 'COMPLETED'],
        ]];
        $this->prepareChangedSandboxGroup('merchant-id', 'serialized', $scopes);
        $this->paypalMerchantResolver->method('getPaypalMerchantForExactScope')
            ->willReturn($this->createPaypalMerchant('paypal-id', PaypalMerchantInterface::COMPLETED_STATUS));
        $this->metadataService->method('upsert')->willReturn(false);
        $this->flagManager->expects($this->never())->method('saveFlag');

        $this->synchronizer->synchronize();
    }

    /**
     * @dataProvider changedPayloadProvider
     */
    public function testMerchantUrlOrScopeChangesCreateANewDigest(
        string $merchantId,
        string $serializedMetadata
    ): void {
        $oldDigest = hash('sha256', implode("\0", ['v1', 'old-merchant', 'old-payload']));
        $scopes = [[
            'scopeType' => 'GLOBAL',
            'scopeId' => 0,
            'paypal-account' => ['id' => 'paypal-id', 'status' => 'COMPLETED'],
        ]];
        $this->prepareChangedSandboxGroup($merchantId, $serializedMetadata, $scopes, $oldDigest);
        $this->paypalMerchantResolver->method('getPaypalMerchantForExactScope')
            ->willReturn($this->createPaypalMerchant('paypal-id', PaypalMerchantInterface::COMPLETED_STATUS));
        $this->metadataService->expects($this->once())->method('upsert')->willReturn(true);
        $this->flagManager->expects($this->once())->method('saveFlag')->with(
            'payment_services_metadata_digest_sandbox',
            hash('sha256', implode("\0", ['v1', $merchantId, $serializedMetadata]))
        );

        $this->synchronizer->synchronize();
    }

    public static function changedPayloadProvider(): array
    {
        return [
            'merchant ID changed' => ['new-merchant', 'old-payload'],
            'URL changed' => ['old-merchant', 'payload-with-new-url'],
            'scope changed' => ['old-merchant', 'payload-with-new-scope'],
        ];
    }

    public function testMixedMethodsProcessEachEnvironmentOnce(): void
    {
        $sandboxMetadata = self::METADATA;
        $productionMetadata = [[
            'key' => 'web/secure/base_url',
            'value' => 'https://production.example.com',
            'scopeType' => 'WEBSITE',
            'scopeId' => '2',
        ]];
        $this->metadataProvider->method('getMetadataByEnvironment')->willReturn([
            'sandbox' => $sandboxMetadata,
            'production' => $productionMetadata,
        ]);
        $this->config->method('getMerchantId')
            ->willReturnCallback(static fn (string $environment): string => $environment . '-merchant');
        $this->config->method('isServicesConnectorConfigured')->willReturn(true);
        $this->serializer->method('serialize')->willReturnMap([
            [$sandboxMetadata, 'sandbox-payload'],
            [$productionMetadata, 'production-payload'],
        ]);
        $this->flagManager->method('getFlagData')->willReturn(null);
        $scopes = [[
            'scopeType' => 'GLOBAL',
            'scopeId' => 0,
            'paypal-account' => ['id' => 'paypal-id', 'status' => 'COMPLETED'],
        ]];
        $this->merchantService->expects($this->exactly(2))
            ->method('getAllScopesForMerchant')
            ->willReturn($scopes);
        $this->paypalMerchantResolver->method('getPaypalMerchantForExactScope')
            ->willReturn($this->createPaypalMerchant('paypal-id', PaypalMerchantInterface::COMPLETED_STATUS));
        $upserts = [];
        $this->metadataService->expects($this->exactly(2))
            ->method('upsert')
            ->willReturnCallback(static function (array $metadata, string $environment) use (&$upserts): bool {
                $upserts[$environment] = $metadata;
                return true;
            });
        $this->flagManager->expects($this->exactly(2))->method('saveFlag');

        $this->synchronizer->synchronize();

        $this->assertSame([
            'sandbox' => $sandboxMetadata,
            'production' => $productionMetadata,
        ], $upserts);
    }

    public function testMissingConfigurationSkipsEnvironment(): void
    {
        $this->metadataProvider->method('getMetadataByEnvironment')->willReturn([
            'production' => self::METADATA,
        ]);
        $this->config->method('getMerchantId')->willReturn('');
        $this->config->expects($this->never())->method('isServicesConnectorConfigured');
        $this->flagManager->expects($this->never())->method('getFlagData');
        $this->merchantService->expects($this->never())->method('getAllScopesForMerchant');

        $this->synchronizer->synchronize();
    }

    private function prepareChangedSandboxGroup(
        string $merchantId,
        string $serializedMetadata,
        array $scopes,
        ?string $previousDigest = null
    ): void {
        $this->metadataProvider->method('getMetadataByEnvironment')->willReturn([
            'sandbox' => self::METADATA,
        ]);
        $this->config->method('getMerchantId')->with('sandbox')->willReturn($merchantId);
        $this->config->method('isServicesConnectorConfigured')->with('sandbox')->willReturn(true);
        $this->serializer->method('serialize')->with(self::METADATA)->willReturn($serializedMetadata);
        $this->flagManager->method('getFlagData')->willReturn($previousDigest);
        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->with('sandbox', true)
            ->willReturn($scopes);
    }

    private function createPaypalMerchant(string $id, string $status): PaypalMerchantInterface
    {
        $merchant = $this->createMock(PaypalMerchantInterface::class);
        $merchant->method('getId')->willReturn($id);
        $merchant->method('getStatus')->willReturn($status);
        return $merchant;
    }
}

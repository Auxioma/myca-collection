<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
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

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\MerchantCacheService;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MerchantServiceTest extends TestCase
{
    /**
     * @var MerchantService
     */
    private MerchantService $merchantService;

    /**
     * @var Config|MockObject
     */
    private Config $config;

    /**
     * @var ServiceClientInterface|MockObject
     */
    private ServiceClientInterface $serviceClient;

    /**
     * @var WriterInterface|MockObject
     */
    private WriterInterface $configWriter;

    /**
     * @var TypeListInterface|MockObject
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var MerchantCacheService|MockObject
     */
    private MerchantCacheService $cache;

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->serviceClient = $this->createMock(ServiceClientInterface::class);
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->cache = $this->createMock(MerchantCacheService::class);

        $this->merchantService = new MerchantService(
            $this->config,
            $this->serviceClient,
            $this->configWriter,
            $this->cacheTypeList,
            $this->cache
        );
    }

    /**
     * @return void
     */
    public function testResetMerchantId(): void
    {
        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->willReturn('merchant_id');
        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willReturn(['is_successful' => true, 'status'=>200]);

        $this->configWriter->expects($this->once())
            ->method($this->anything());

        $result = $this->merchantService->delete('sandbox');

        $this->assertTrue($result['is_successful']);
    }

    /**
     * @return void
     */
    public function testResetMerchantIdWithWrongEnvironment(): void
    {
        $this->config->expects($this->never())
            ->method($this->anything());

        $this->serviceClient->expects($this->never())
            ->method($this->anything());

        $this->configWriter->expects($this->never())
            ->method($this->anything());

        $result = $this->merchantService->delete('integration');

        $this->assertFalse($result['is_successful']);
    }

    /**
     * @return void
     */
    public function testGetAllScopesForMerchantWillCallApiSuccessfully(): void
    {
        $scopes = ["scope1", "scope2"];

        $this->cache->expects($this->once())
            ->method("loadScopesFromCache")
            ->willReturn([]);

        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with('test')
            ->willReturn('merchant_id');

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'is_successful' => true,
                'mp-merchant' =>
                    ['merchant-scope' => $scopes],
                'status'=>200
            ]);

        $this->cache->expects($this->once())
            ->method('saveScopesToCache')
            ->with($scopes, 'test');

        $this->assertEquals($scopes, $this->merchantService->getAllScopesForMerchant('test'));
    }

    /**
     * @return void
     */
    public function testGetAllScopesForMerchantWillCallApiWithError(): void
    {
        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with('test')
            ->willReturn('merchant_id');

        $this->cache->expects($this->once())
            ->method("loadScopesFromCache")
            ->willReturn([]);

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'is_successful' => false,
                'status' => 200
            ]);

        $this->cache->expects($this->never())
            ->method('saveScopesToCache');

        $this->assertEquals([], $this->merchantService->getAllScopesForMerchant('test'));
    }

    /**
     * @return void
     */
    public function testGetAllScopesForMerchantWillLoadFromCache(): void
    {
        $scopes = ["scope1", "scope2"];

        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with('test')
            ->willReturn('merchant_id');

        $this->cache->expects($this->once())
            ->method("loadScopesFromCache")
            ->willReturn($scopes);

        $this->serviceClient->expects($this->never())
            ->method('request');

        $this->cache->expects($this->never())
            ->method('saveScopesToCache');

        $this->assertEquals($scopes, $this->merchantService->getAllScopesForMerchant('test'));
    }

    /**
     * @return void
     */
    public function testGetAllScopesForMerchantWithMerchantIdIsNull(): void
    {
        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with('test')
            ->willReturn("");

        $this->cache->expects($this->never())
            ->method("loadScopesFromCache");

        $this->serviceClient->expects($this->never())
            ->method('request');

        $this->cache->expects($this->never())
            ->method('saveScopesToCache');

        $this->assertEquals([], $this->merchantService->getAllScopesForMerchant('test'));
    }

    /**
     * @return void
     */
    public function testGetAllScopesForMerchantUsesProvidedEnvironment(): void
    {
        $scopes = ["scope1", "scope2"];

        $this->config->expects($this->never())
            ->method('getEnvironmentType');

        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with('production')
            ->willReturn('merchant_id');

        $this->cache->expects($this->once())
            ->method("loadScopesFromCache")
            ->with('production')
            ->willReturn([]);

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->with(
                ['Content-Type' => 'application/json', 'x-mp-merchant-id' => 'merchant_id'],
                '/config/scopes/merchant/merchant_id',
                'GET',
                '',
                'json',
                'production'
            )
            ->willReturn([
                'is_successful' => true,
                'mp-merchant' =>
                    ['merchant-scope' => $scopes],
                'status' => 200
            ]);

        $this->cache->expects($this->once())
            ->method('saveScopesToCache')
            ->with($scopes, 'production');

        $this->assertEquals($scopes, $this->merchantService->getAllScopesForMerchant('production'));
    }

    /**
     * @return void
     */
    public function testGetAllScopesForMerchantForceRefreshSkipsCacheAndCallsApiSuccessfully(): void
    {
        $scopes = ["scope1", "scope2"];

        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with('test')
            ->willReturn('merchant_id');

        $this->cache->expects($this->never())
            ->method("loadScopesFromCache");

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'is_successful' => true,
                'mp-merchant' =>
                    ['merchant-scope' => $scopes],
                'status' => 200
            ]);

        $this->cache->expects($this->once())
            ->method('saveScopesToCache')
            ->with($scopes, 'test');

        $this->assertEquals($scopes, $this->merchantService->getAllScopesForMerchant('test', true));
    }

    /**
     * @return void
     */
    public function testGetAllScopesForMerchantForceRefreshWithApiErrorDoesNotSaveCache(): void
    {
        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with('test')
            ->willReturn('merchant_id');

        $this->cache->expects($this->never())
            ->method("loadScopesFromCache");

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'is_successful' => false,
                'status' => 200
            ]);

        $this->cache->expects($this->never())
            ->method('saveScopesToCache');

        $this->assertEquals([], $this->merchantService->getAllScopesForMerchant('test', true));
    }
}

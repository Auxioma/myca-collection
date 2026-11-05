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

use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\PaymentServicesPaypal\Model\Metadata\MetadataService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MetadataServiceTest extends TestCase
{
    /** @var ServiceClientInterface|MockObject */
    private ServiceClientInterface|MockObject $serviceClient;

    /** @var Json|MockObject */
    private Json|MockObject $serializer;

    /** @var MetadataService */
    private MetadataService $metadataService;

    protected function setUp(): void
    {
        $this->serviceClient = $this->createMock(ServiceClientInterface::class);
        $this->serializer = $this->createMock(Json::class);
        $this->metadataService = new MetadataService($this->serviceClient, $this->serializer);
    }

    /**
     * @dataProvider successfulResponseProvider
     */
    public function testUpsertUsesRawResponseModeForEmptySuccessfulResponses(int $status): void
    {
        $metadata = [['key' => 'web/secure/base_url', 'value' => 'https://example.com']];
        $body = '{"metadata":[]}';
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with(['metadata' => $metadata])
            ->willReturn($body);
        $this->serviceClient->expects($this->once())
            ->method('request')
            ->with(
                ['Content-Type' => 'application/json'],
                '/admin/metadata',
                Http::METHOD_PUT,
                $body,
                'raw',
                'production'
            )
            ->willReturn(['is_successful' => true, 'status' => $status]);

        $this->assertTrue($this->metadataService->upsert($metadata, 'production'));
    }

    public static function successfulResponseProvider(): array
    {
        return [
            'empty 200 response' => [200],
            'empty 204 response' => [204],
        ];
    }

    public function testUpsertReturnsFalseForUnsuccessfulResponse(): void
    {
        $this->serializer->method('serialize')->willReturn('{"metadata":[]}');
        $this->serviceClient->method('request')->willReturn([
            'is_successful' => false,
            'status' => 500,
        ]);

        $this->assertFalse($this->metadataService->upsert([], 'sandbox'));
    }
}

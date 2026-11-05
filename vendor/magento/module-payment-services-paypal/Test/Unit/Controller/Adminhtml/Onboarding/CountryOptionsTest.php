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

namespace Magento\PaymentServicesPaypal\Test\Unit\Controller\Adminhtml\Onboarding;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\PaymentServicesPaypal\Controller\Adminhtml\Onboarding\CountryOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CountryOptionsTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private Context $context;

    /**
     * @var RequestHttp|MockObject
     */
    private RequestHttp $request;

    /**
     * @var ResultFactory|MockObject
     */
    private ResultFactory $resultFactory;

    /**
     * @var JsonResult|MockObject
     */
    private JsonResult $jsonResult;

    /**
     * @var ServiceClientInterface|MockObject
     */
    private ServiceClientInterface $serviceClient;

    /**
     * @var ResolverInterface|MockObject
     */
    private ResolverInterface $localeResolver;

    /**
     * @var PaymentServicesConfig|MockObject
     */
    private PaymentServicesConfig $config;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var CountryOptions
     */
    private CountryOptions $controller;

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(RequestHttp::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->jsonResult = $this->createMock(JsonResult::class);
        $this->serviceClient = $this->createMock(ServiceClientInterface::class);
        $this->localeResolver = $this->createMock(ResolverInterface::class);
        $this->config = $this->createMock(PaymentServicesConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->context->method('getRequest')
            ->willReturn($this->request);
        $this->context->method('getResultFactory')
            ->willReturn($this->resultFactory);

        $this->controller = new CountryOptions(
            $this->context,
            $this->serviceClient,
            $this->localeResolver,
            $this->config,
            $this->logger
        );
    }

    /**
     * @return void
     */
    public function testExecuteReturnsCountryOptionsForValidRequest(): void
    {
        $payload = [
            'result' => [
                'status' => 'OK',
                'countries' => [
                    ['code' => 'US', 'name' => 'United States', 'ppcp' => true],
                ],
            ],
        ];

        $this->mockRequestParams(['websiteId' => '3']);

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(3)
            ->willReturn(PaymentServicesConfig::PRODUCTION_ENVIRONMENT);

        $this->localeResolver->expects($this->once())
            ->method('getLocale')
            ->willReturn('en_US');

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->with(
                ['Content-Type' => 'application/json'],
                '/onboarding/status?shallow=true&locale=en-US',
                RequestHttp::METHOD_GET,
                '',
                'json',
                'production'
            )
            ->willReturn([
                'is_successful' => true,
                'status' => 200,
            ] + $payload);

        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->jsonResult);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($payload)
            ->willReturnSelf();

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * @return void
     */
    public function testExecuteReturnsBadRequestForInvalidWebsiteId(): void
    {
        $this->mockRequestParams(['websiteId' => '0']);

        $this->config->expects($this->never())
            ->method('getEnvironmentTypeForWebsite');
        $this->serviceClient->expects($this->never())
            ->method('request');

        $this->expectJsonErrorResult(400, 'Invalid onboarding country options request.');

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    public function testExecuteReturnsBadGatewayWhenServiceResponseFails(): void
    {
        $this->mockRequestParams(['websiteId' => '3']);

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(3)
            ->willReturn(PaymentServicesConfig::SANDBOX_ENVIRONMENT);

        $this->localeResolver->expects($this->once())
            ->method('getLocale')
            ->willReturn('en_US');

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'is_successful' => false,
                'status' => 503,
            ]);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'PayPal scope onboarding country options request was unsuccessful.',
                [
                    'website_id' => 3,
                    'environment' => 'sandbox',
                    'status' => 503,
                ]
            );

        $this->expectJsonErrorResult(502, 'Unable to retrieve PayPal onboarding country options.');

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * @return void
     */
    public function testExecuteReturnsBadGatewayWhenServiceThrowsException(): void
    {
        $this->mockRequestParams(['websiteId' => '3']);

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(3)
            ->willReturn(PaymentServicesConfig::SANDBOX_ENVIRONMENT);

        $this->localeResolver->expects($this->once())
            ->method('getLocale')
            ->willReturn('en_US');

        $exception = new RuntimeException('Service unavailable');

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'PayPal scope onboarding country options failed.',
                [
                    'website_id' => 3,
                    'environment' => 'sandbox',
                    'message' => $exception->getMessage(),
                ]
            );

        $this->expectJsonErrorResult(502, 'Unable to retrieve PayPal onboarding country options.');

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * Mock request parameters.
     *
     * @param array $params
     * @return void
     */
    private function mockRequestParams(array $params): void
    {
        $this->request->method('getParam')
            ->willReturnCallback(static function (string $key, $default = null) use ($params) {
                return array_key_exists($key, $params) ? $params[$key] : $default;
            });
    }

    /**
     * Expect a JSON error result.
     *
     * @param int $httpStatus
     * @param string $message
     * @return void
     */
    private function expectJsonErrorResult(int $httpStatus, string $message): void
    {
        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->jsonResult);

        $this->jsonResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with($httpStatus)
            ->willReturnSelf();

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with([
                'error' => true,
                'message' => $message,
            ])
            ->willReturnSelf();
    }
}

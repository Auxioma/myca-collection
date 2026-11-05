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
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\PaymentServicesPaypal\Controller\Adminhtml\Onboarding\Scope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ScopeTest extends TestCase
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
     * @var Raw|MockObject
     */
    private Raw $rawResult;

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
     * @var Scope
     */
    private Scope $controller;

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(RequestHttp::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->jsonResult = $this->createMock(JsonResult::class);
        $this->rawResult = $this->createMock(Raw::class);
        $this->serviceClient = $this->createMock(ServiceClientInterface::class);
        $this->localeResolver = $this->createMock(ResolverInterface::class);
        $this->config = $this->createMock(PaymentServicesConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->context->method('getRequest')
            ->willReturn($this->request);
        $this->context->method('getResultFactory')
            ->willReturn($this->resultFactory);

        $this->controller = new Scope(
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
    public function testExecuteReturnsAjaxRedirectForValidRequest(): void
    {
        $redirect = ['url' => 'https://www.paypal.com/partner-referral'];

        $this->mockRequestParams([
            'websiteId' => '7',
            'country' => 'us',
            'type' => 'ppcp',
        ]);
        $this->request->expects($this->once())
            ->method('isAjax')
            ->willReturn(true);

        $this->localeResolver->expects($this->once())
            ->method('getLocale')
            ->willReturn('en_US');

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(7)
            ->willReturn(PaymentServicesConfig::SANDBOX_ENVIRONMENT);

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->with(
                [
                    'Content-Type' => 'application/json',
                    ScopeHeadersBuilder::SCOPE_TYPE => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
                    ScopeHeadersBuilder::SCOPE_ID => '7',
                ],
                '/onboarding/paypal/scope?locale=en-US&country=US&type=PPCP',
                RequestHttp::METHOD_POST,
                '',
                'json',
                'sandbox'
            )
            ->willReturn([
                'is_successful' => true,
                'redirect' => $redirect,
            ]);

        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->jsonResult);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(['redirect' => $redirect])
            ->willReturnSelf();

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * @return void
     */
    public function testExecuteReturnsBadRequestForInvalidRequestParams(): void
    {
        $this->mockRequestParams([
            'websiteId' => '0',
            'country' => 'US',
            'type' => 'PPCP',
        ]);

        $this->config->expects($this->never())
            ->method('getEnvironmentTypeForWebsite');
        $this->serviceClient->expects($this->never())
            ->method('request');

        $this->expectRawErrorResult(400, 'Invalid scope onboarding request.');

        $this->assertSame($this->rawResult, $this->controller->execute());
    }

    public function testExecuteReturnsBadGatewayWhenServiceDoesNotReturnValidRedirect(): void
    {
        $this->mockRequestParams([
            'websiteId' => '7',
            'country' => 'US',
            'type' => 'PPCP',
        ]);

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(7)
            ->willReturn(PaymentServicesConfig::SANDBOX_ENVIRONMENT);

        $this->localeResolver->expects($this->once())
            ->method('getLocale')
            ->willReturn('en_US');

        $this->serviceClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'is_successful' => true,
                'redirect' => ['url' => ''],
            ]);

        $this->expectRawErrorResult(502, 'PayPal onboarding URL was not returned by the service.');

        $this->assertSame($this->rawResult, $this->controller->execute());
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
     * Expect a raw error result.
     *
     * @param int $httpStatus
     * @param string $message
     * @return void
     */
    private function expectRawErrorResult(int $httpStatus, string $message): void
    {
        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->rawResult);

        $this->rawResult->expects($this->once())
            ->method('setHttpResponseCode')
            ->with($httpStatus)
            ->willReturnSelf();

        $this->rawResult->expects($this->once())
            ->method('setContents')
            ->with($message)
            ->willReturnSelf();
    }
}

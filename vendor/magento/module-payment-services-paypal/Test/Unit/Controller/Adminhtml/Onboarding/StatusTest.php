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
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Controller\Adminhtml\Onboarding\Status;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class StatusTest extends TestCase
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
     * @var MerchantService|MockObject
     */
    private MerchantService $merchantService;

    /**
     * @var PaypalMerchantResolver|MockObject
     */
    private PaypalMerchantResolver $merchantResolver;

    /**
     * @var PaymentServicesConfig|MockObject
     */
    private PaymentServicesConfig $config;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var Status
     */
    private Status $controller;

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(RequestHttp::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->jsonResult = $this->createMock(JsonResult::class);
        $this->merchantService = $this->createMock(MerchantService::class);
        $this->merchantResolver = $this->createMock(PaypalMerchantResolver::class);
        $this->config = $this->createMock(PaymentServicesConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->context->method('getRequest')
            ->willReturn($this->request);
        $this->context->method('getResultFactory')
            ->willReturn($this->resultFactory);

        $this->controller = new Status(
            $this->context,
            $this->merchantService,
            $this->merchantResolver,
            $this->config,
            $this->logger
        );
    }

    /**
     * @return void
     */
    public function testExecuteReturnsActiveWebsiteScopeStatusWithForceRefresh(): void
    {
        $this->mockRequestParams([
            'websiteId' => '2',
            'forceRefresh' => 'true',
        ]);

        $scopes = [
            [
                'scopeId' => 0,
                'scopeType' => 'global',
                'paypal-account' => [
                    'id' => 'global-paypal-id',
                    'status' => PaypalMerchantInterface::COMPLETED_STATUS,
                ],
            ],
            [
                'scopeId' => 2,
                'scopeType' => 'WEBSITE',
                'paypal-account' => [
                    'id' => 'website-paypal-id',
                    'status' => PaypalMerchantInterface::COMPLETED_STATUS,
                ],
            ],
        ];

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(2)
            ->willReturn(PaymentServicesConfig::SANDBOX_ENVIRONMENT);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->with('sandbox', true)
            ->willReturn($scopes);

        $this->merchantResolver->expects($this->once())
            ->method('getPaypalMerchantForExactScope')
            ->with($scopes, 2, ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE)
            ->willReturn($this->createPayPalMerchant(
                'website-paypal-id',
                PaypalMerchantInterface::COMPLETED_STATUS
            ));

        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->jsonResult);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with([
                'scopeType' => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
                'scopeId' => 2,
                'paypalMerchantId' => 'website-paypal-id',
                'status' => PaypalMerchantInterface::COMPLETED_STATUS,
                'isActive' => true,
            ])
            ->willReturnSelf();

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * @return void
     */
    public function testExecuteDoesNotFallbackToGlobalScopeWhenWebsiteScopeIsMissing(): void
    {
        $this->mockRequestParams([
            'websiteId' => '5',
        ]);

        $scopes = [
            [
                'scopeId' => 0,
                'scopeType' => 'GLOBAL',
                'paypal-account' => [
                    'id' => 'global-paypal-id',
                    'status' => PaypalMerchantInterface::COMPLETED_STATUS,
                ],
            ],
        ];

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(5)
            ->willReturn(PaymentServicesConfig::SANDBOX_ENVIRONMENT);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->with('sandbox', false)
            ->willReturn($scopes);

        $this->merchantResolver->expects($this->once())
            ->method('getPaypalMerchantForExactScope')
            ->with($scopes, 5, ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE)
            ->willReturn(null);

        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->jsonResult);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with([
                'scopeType' => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
                'scopeId' => 5,
                'paypalMerchantId' => null,
                'status' => null,
                'isActive' => false,
            ])
            ->willReturnSelf();

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    /**
     * @return void
     */
    public function testExecuteReturnsBadRequestForInvalidWebsiteId(): void
    {
        $this->mockRequestParams([
            'websiteId' => '0',
        ]);

        $this->config->expects($this->never())
            ->method('getEnvironmentTypeForWebsite');
        $this->merchantService->expects($this->never())
            ->method('getAllScopesForMerchant');
        $this->merchantResolver->expects($this->never())
            ->method('getPaypalMerchantForExactScope');

        $this->expectJsonErrorResult(400, 'Invalid onboarding status request.');

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    public function testExecuteReturnsBadGatewayWhenMerchantServiceFails(): void
    {
        $this->mockRequestParams([
            'websiteId' => '2',
            'forceRefresh' => '1',
        ]);

        $exception = new RuntimeException('Service unavailable');

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(2)
            ->willReturn(PaymentServicesConfig::PRODUCTION_ENVIRONMENT);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->with('production', true)
            ->willThrowException($exception);

        $this->merchantResolver->expects($this->never())
            ->method('getPaypalMerchantForExactScope');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'PayPal scope onboarding status failed:',
                $this->callback(static function (array $context) use ($exception): bool {
                    return ($context['website_id'] ?? null) === 2
                        && ($context['environment'] ?? null) === 'production'
                        && ($context['message'] ?? null) === $exception->getMessage();
                })
            );

        $this->expectJsonErrorResult(502, 'Unable to retrieve PayPal onboarding status.');

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

    /**
     * Create a PayPal merchant mock.
     *
     * @param string|null $merchantId
     * @param string|null $status
     * @return PaypalMerchantInterface
     */
    private function createPayPalMerchant(?string $merchantId, ?string $status): PaypalMerchantInterface
    {
        $merchant = $this->createMock(PaypalMerchantInterface::class);
        $merchant->method('getId')
            ->willReturn($merchantId);
        $merchant->method('getStatus')
            ->willReturn($status);

        return $merchant;
    }
}

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

namespace Magento\PaymentServicesPaypal\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\PaymentServicesBase\Model\Config as PaymentServicesConfig;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ScopeOnboardingTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private Context $context;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var PaypalMerchantResolver|MockObject
     */
    private PaypalMerchantResolver $merchantResolver;

    /**
     * @var PaymentServicesConfig|MockObject
     */
    private PaymentServicesConfig $config;

    /**
     * @var ObjectManagerInterface|MockObject
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var TestableScopeOnboarding
     */
    private TestableScopeOnboarding $block;

    /**
     * Set up the test.
     */
    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->merchantResolver = $this->createMock(PaypalMerchantResolver::class);
        $this->config = $this->createMock(PaymentServicesConfig::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->context->method('getScopeConfig')
            ->willReturn($this->scopeConfig);

        $this->mockObjectManagerFallbacks();

        $this->block = new TestableScopeOnboarding(
            $this->merchantResolver,
            $this->logger,
            $this->config,
            $this->context
        );
    }

    /**
     * @return void
     */
    public function testRenderReturnsEmptyForNonWebsiteScope(): void
    {
        $this->scopeConfig->expects($this->never())
            ->method('getValue');
        $this->merchantResolver->expects($this->never())
            ->method('getPayPalMerchant');
        $this->merchantResolver->expects($this->never())
            ->method('getExactPayPalMerchant');

        $this->assertSame('', $this->block->render($this->createElement('default', 0)));
    }

    /**
     * @return void
     */
    public function testRenderReturnsEmptyWhenGlobalMerchantIdIsMissing(): void
    {
        $this->mockWebsiteMultiBusinessAccountScopingLevel();

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with(3)
            ->willReturn(PaymentServicesConfig::SANDBOX_ENVIRONMENT);

        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with(PaymentServicesConfig::SANDBOX_ENVIRONMENT)
            ->willReturn('');

        $this->merchantResolver->expects($this->never())
            ->method('getExactPayPalMerchant');

        $this->assertSame('', $this->block->render($this->createElement(ScopeInterface::SCOPE_WEBSITES, 3)));
    }

    /**
     * @return void
     */
    public function testRenderReturnsEmptyWhenMultiBusinessAccountScopingIsStoreViewLevel(): void
    {
        $this->config->expects($this->once())
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_STORE);

        $this->config->expects($this->never())
            ->method('getEnvironmentTypeForWebsite');
        $this->config->expects($this->never())
            ->method('getMerchantId');

        $this->merchantResolver->expects($this->never())
            ->method('getExactPayPalMerchant');

        $this->assertSame('', $this->block->render($this->createElement(ScopeInterface::SCOPE_WEBSITES, 3)));
    }

    /**
     * @return void
     */
    public function testRenderReturnsEmptyWhenExactWebsitePayPalMerchantIsActive(): void
    {
        $this->mockConfiguredPaymentServicesMerchantId(3);

        $this->merchantResolver->expects($this->once())
            ->method('getExactPayPalMerchant')
            ->with(
                ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
                3,
                PaymentServicesConfig::SANDBOX_ENVIRONMENT
            )
            ->willReturn($this->createPayPalMerchant('website-paypal-id', PaypalMerchantInterface::COMPLETED_STATUS));

        $this->assertSame('', $this->block->render($this->createElement(ScopeInterface::SCOPE_WEBSITES, 3)));
    }

    /**
     * @return void
     */
    public function testRenderReturnsEmptyAndLogsWarningWhenWebsitePayPalMerchantCannotBeResolved(): void
    {
        $this->mockConfiguredPaymentServicesMerchantId(2);

        $exception = new RuntimeException('Unable to load website merchant.');

        $this->merchantResolver->expects($this->once())
            ->method('getExactPayPalMerchant')
            ->with(
                ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
                2,
                PaymentServicesConfig::SANDBOX_ENVIRONMENT
            )
            ->willThrowException($exception);

        $this->expectVisibilityWarning(2, PaymentServicesConfig::SANDBOX_ENVIRONMENT, $exception);

        $this->assertSame('', $this->block->render($this->createElement(ScopeInterface::SCOPE_WEBSITES, 2)));
    }

    /**
     * @return void
     */
    public function testGetElementHtmlAddsScopeOnboardingUrls(): void
    {
        $html = $this->block->getElementHtml($this->createElement(ScopeInterface::SCOPE_WEBSITES, 11));

        $this->assertSame('scope-onboarding-html', $html);
        $this->assertSame(
            'https://admin.example/paymentservicespaypal/onboarding/status'
            . '?forceRefresh=true&websiteId=11',
            $this->block->getData('get_onboarding_status_url')
        );
        $this->assertSame(
            'https://admin.example/paymentservicespaypal/onboarding/scope?websiteId=11',
            $this->block->getData('scope_onboarding_url')
        );
        $this->assertSame(
            'https://admin.example/paymentservicespaypal/onboarding/countryOptions?websiteId=11&isAjax=true',
            $this->block->getData('onboarding_country_options_url')
        );
    }

    /**
     * Mock fallback services requested by parent Magento block constructors.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @return void
     */
    private function mockObjectManagerFallbacks(): void
    {
        $jsonHelper = $this->createMock(JsonHelper::class);
        $directoryHelper = $this->createMock(DirectoryHelper::class);
        $secureHtmlRenderer = $this->createMock(SecureHtmlRenderer::class);

        $this->objectManager->method('get')
            ->willReturnCallback(
                static function (string $type) use ($jsonHelper, $directoryHelper, $secureHtmlRenderer) {
                    if ($type === JsonHelper::class) {
                        return $jsonHelper;
                    }

                    if ($type === DirectoryHelper::class) {
                        return $directoryHelper;
                    }

                    if ($type === SecureHtmlRenderer::class) {
                        return $secureHtmlRenderer;
                    }

                    return null;
                }
            );

        ObjectManager::setInstance($this->objectManager);
    }

    /**
     * Mock the selected website environment and Payment Services merchant ID.
     *
     * @param int $websiteId
     * @param string $environment
     * @return void
     */
    private function mockConfiguredPaymentServicesMerchantId(
        int $websiteId,
        string $environment = PaymentServicesConfig::SANDBOX_ENVIRONMENT
    ): void {
        $this->mockWebsiteMultiBusinessAccountScopingLevel();

        $this->config->expects($this->once())
            ->method('getEnvironmentTypeForWebsite')
            ->with($websiteId)
            ->willReturn($environment);

        $this->config->expects($this->once())
            ->method('getMerchantId')
            ->with($environment)
            ->willReturn($environment . '-merchant-id');
    }

    /**
     * Mock Payment Services multi-business account scoping at website level.
     *
     * @return void
     */
    private function mockWebsiteMultiBusinessAccountScopingLevel(): void
    {
        $this->config->expects($this->once())
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Expect a visibility warning log entry.
     *
     * @param int $websiteId
     * @param string $environment
     * @param RuntimeException $exception
     * @return void
     */
    private function expectVisibilityWarning(
        int $websiteId,
        string $environment,
        RuntimeException $exception
    ): void {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Unable to determine PayPal scope onboarding button visibility.',
                $this->callback(static function (array $context) use ($websiteId, $environment, $exception): bool {
                    return ($context['website_id'] ?? null) === $websiteId
                        && ($context['environment'] ?? null) === $environment
                        && ($context['message'] ?? null) === $exception->getMessage();
                })
            );
    }

    /**
     * Create a form element mock.
     *
     * @param string $scope
     * @param int $scopeId
     * @return TestableScopeOnboardingElement
     */
    private function createElement(string $scope, int $scopeId): TestableScopeOnboardingElement
    {
        return new TestableScopeOnboardingElement($scope, $scopeId);
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

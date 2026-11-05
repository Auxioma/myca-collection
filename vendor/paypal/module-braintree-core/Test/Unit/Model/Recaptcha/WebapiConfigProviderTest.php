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

namespace PayPal\Braintree\Test\Unit\Model\Recaptcha;

use Magento\Framework\Webapi\Rest\Request;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use PayPal\Braintree\Model\Recaptcha\WebapiConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WebapiConfigProviderTest extends TestCase
{
    /**
     * @var Request|MockObject
     */
    private Request|MockObject $request;

    /**
     * @var IsCaptchaEnabledInterface|MockObject
     */
    private IsCaptchaEnabledInterface|MockObject $isEnabled;

    /**
     * @var ValidationConfigResolverInterface|MockObject
     */
    private ValidationConfigResolverInterface|MockObject $configResolver;

    /**
     * @var EndpointInterface|MockObject
     */
    private EndpointInterface|MockObject $endpoint;

    /**
     * @var WebapiConfigProvider
     */
    private WebapiConfigProvider $webapiConfigProvider;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->isEnabled = $this->createMock(IsCaptchaEnabledInterface::class);
        $this->configResolver = $this->createMock(ValidationConfigResolverInterface::class);
        $this->endpoint = $this->createMock(EndpointInterface::class);

        $this->webapiConfigProvider = new WebapiConfigProvider(
            $this->request,
            $this->isEnabled,
            $this->configResolver
        );
    }

    /**
     * Test that validation config is returned for Braintree REST payment when captcha is enabled.
     *
     * The braintree payment method passes the isBraintreePaymentRestRequest() check,
     * so execution reaches the captcha-enabled check and returns config.
     */
    #[DataProvider('braintreeRestPaymentDataProvider')]
    public function testGetConfigForReturnsConfigForBraintreeRestPayment(string $serviceMethod): void
    {
        $validationConfig = $this->createMock(ValidationConfigInterface::class);

        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->endpoint->method('getServiceClass')
            ->willReturn('SomeOtherClass');

        $this->request->method('getRequestData')
            ->willReturn([
                'paymentMethod' => [
                    'method' => 'braintree'
                ]
            ]);

        $this->isEnabled->expects(static::once())
            ->method('isCaptchaEnabledFor')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn(true);

        $this->configResolver->expects(static::once())
            ->method('get')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn($validationConfig);

        static::assertSame($validationConfig, $this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * @return array
     */
    public static function braintreeRestPaymentDataProvider(): array
    {
        return [
            'savePaymentInformationAndPlaceOrder method' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder'
            ],
            'placeOrder method' => [
                'serviceMethod' => 'placeOrder'
            ]
        ];
    }

    /**
     * Test that null is returned for Braintree REST payment when captcha is disabled.
     *
     * The request IS a Braintree payment (passes first if), but captcha is disabled
     * for the braintree CAPTCHA_ID, so no config is returned.
     */
    #[DataProvider('braintreeRestPaymentDataProvider')]
    public function testGetConfigForReturnsNullWhenCaptchaDisabledForBraintree(string $serviceMethod): void
    {
        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->endpoint->method('getServiceClass')
            ->willReturn('SomeOtherClass');

        $this->request->method('getRequestData')
            ->willReturn([
                'paymentMethod' => [
                    'method' => 'braintree'
                ]
            ]);

        $this->isEnabled->expects(static::once())
            ->method('isCaptchaEnabledFor')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn(false);

        $this->configResolver->expects(static::never())
            ->method('get');

        static::assertNull($this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * Test that null is returned for non-Braintree REST payment requests (early return).
     *
     * The first if block detects a checkout endpoint with a non-Braintree payment method
     * and returns null immediately — captcha check is never reached.
     */
    #[DataProvider('nonBraintreeRestPaymentDataProvider')]
    public function testGetConfigForReturnsNullForNonBraintreeRestPayment(
        string $serviceMethod,
        array $requestData
    ): void {
        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->endpoint->method('getServiceClass')
            ->willReturn('SomeOtherClass');

        $this->request->method('getRequestData')
            ->willReturn($requestData);

        $this->isEnabled->expects(static::never())
            ->method('isCaptchaEnabledFor');

        $this->configResolver->expects(static::never())
            ->method('get');

        static::assertNull($this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * @return array
     */
    public static function nonBraintreeRestPaymentDataProvider(): array
    {
        // Only savePaymentInformationAndPlaceOrder gets the early null return for non-Braintree
        // payment methods. placeOrder has no payment data in the request body (payment was set
        // separately via set-payment-information), so it always proceeds to the captcha check.
        return [
            'savePaymentInformationAndPlaceOrder with checkmo' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'requestData' => [
                    'paymentMethod' => [
                        'method' => 'checkmo'
                    ]
                ]
            ],
            'savePaymentInformationAndPlaceOrder with missing payment method' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'requestData' => []
            ],
            'savePaymentInformationAndPlaceOrder with braintree_applepay' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'requestData' => [
                    'paymentMethod' => [
                        'method' => 'braintree_applepay'
                    ]
                ]
            ]
        ];
    }

    /**
     * Test that placeOrder always proceeds to captcha check regardless of payment data in request.
     *
     * The placeOrder endpoint (PUT /guest-carts/{id}/order) has no payment data in the request
     * body — payment was set separately via set-payment-information. The first if-block (early
     * null return) only applies to savePaymentInformationAndPlaceOrder, so placeOrder always
     * falls through to the captcha-enabled check.
     */
    #[DataProvider('placeOrderAlwaysChecksCaptchaDataProvider')]
    public function testPlaceOrderAlwaysChecksCaptchaWhenEnabled(array $requestData): void
    {
        $validationConfig = $this->createMock(ValidationConfigInterface::class);

        $this->endpoint->method('getServiceMethod')
            ->willReturn('placeOrder');

        $this->endpoint->method('getServiceClass')
            ->willReturn('SomeOtherClass');

        $this->request->method('getRequestData')
            ->willReturn($requestData);

        $this->isEnabled->expects(static::once())
            ->method('isCaptchaEnabledFor')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn(true);

        $this->configResolver->expects(static::once())
            ->method('get')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn($validationConfig);

        static::assertSame($validationConfig, $this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * Test that placeOrder returns null when captcha is disabled, regardless of payment data.
     */
    #[DataProvider('placeOrderAlwaysChecksCaptchaDataProvider')]
    public function testPlaceOrderReturnsNullWhenCaptchaDisabled(array $requestData): void
    {
        $this->endpoint->method('getServiceMethod')
            ->willReturn('placeOrder');

        $this->endpoint->method('getServiceClass')
            ->willReturn('SomeOtherClass');

        $this->request->method('getRequestData')
            ->willReturn($requestData);

        $this->isEnabled->expects(static::once())
            ->method('isCaptchaEnabledFor')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn(false);

        $this->configResolver->expects(static::never())
            ->method('get');

        static::assertNull($this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * @return array
     */
    public static function placeOrderAlwaysChecksCaptchaDataProvider(): array
    {
        return [
            'placeOrder with no request data (bypass scenario)' => [
                'requestData' => []
            ],
            'placeOrder with cashondelivery' => [
                'requestData' => [
                    'paymentMethod' => [
                        'method' => 'cashondelivery'
                    ]
                ]
            ],
            'placeOrder with braintree' => [
                'requestData' => [
                    'paymentMethod' => [
                        'method' => 'braintree'
                    ]
                ]
            ],
            'placeOrder with braintree_paypal' => [
                'requestData' => [
                    'paymentMethod' => [
                        'method' => 'braintree_paypal'
                    ]
                ]
            ],
            'placeOrder with braintree_googlepay' => [
                'requestData' => [
                    'paymentMethod' => [
                        'method' => 'braintree_googlepay'
                    ]
                ]
            ]
        ];
    }

    /**
     * Test that validation config is returned when endpoint matches GraphQL service classes.
     *
     * GraphQL endpoints bypass the REST request check entirely (not a REST checkout method)
     * and go straight to the captcha-enabled check.
     */
    #[DataProvider('graphQlServiceClassDataProvider')]
    public function testGetConfigForReturnsConfigForGraphQlEndpoints(string $serviceClass): void
    {
        $validationConfig = $this->createMock(ValidationConfigInterface::class);

        $this->endpoint->method('getServiceMethod')
            ->willReturn('someOtherMethod');

        $this->endpoint->method('getServiceClass')
            ->willReturn($serviceClass);

        $this->isEnabled->expects(static::once())
            ->method('isCaptchaEnabledFor')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn(true);

        $this->configResolver->expects(static::once())
            ->method('get')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn($validationConfig);

        static::assertSame($validationConfig, $this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * Test that null is returned for GraphQL endpoints when captcha is disabled.
     */
    #[DataProvider('graphQlServiceClassDataProvider')]
    public function testGetConfigForReturnsNullForGraphQlEndpointsWhenCaptchaDisabled(
        string $serviceClass
    ): void {
        $this->endpoint->method('getServiceMethod')
            ->willReturn('someOtherMethod');

        $this->endpoint->method('getServiceClass')
            ->willReturn($serviceClass);

        $this->isEnabled->expects(static::once())
            ->method('isCaptchaEnabledFor')
            ->with(WebapiConfigProvider::CAPTCHA_ID)
            ->willReturn(false);

        $this->configResolver->expects(static::never())
            ->method('get');

        static::assertNull($this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * @return array
     */
    public static function graphQlServiceClassDataProvider(): array
    {
        return [
            'SetPaymentAndPlaceOrder resolver' => [
                'serviceClass' => 'Magento\QuoteGraphQl\Model\Resolver\SetPaymentAndPlaceOrder'
            ],
            'PlaceOrder resolver' => [
                'serviceClass' => 'Magento\QuoteGraphQl\Model\Resolver\PlaceOrder'
            ]
        ];
    }

    /**
     * Test that null is returned when endpoint does not match any known methods or classes.
     *
     * Unrelated endpoints should never trigger captcha checks.
     */
    public function testGetConfigForReturnsNullForNonMatchingEndpoint(): void
    {
        $this->endpoint->method('getServiceMethod')
            ->willReturn('someUnrelatedMethod');

        $this->endpoint->method('getServiceClass')
            ->willReturn('Some\Unrelated\Class');

        $this->isEnabled->expects(static::never())
            ->method('isCaptchaEnabledFor');

        $this->configResolver->expects(static::never())
            ->method('get');

        static::assertNull($this->webapiConfigProvider->getConfigFor($this->endpoint));
    }

    /**
     * Test CAPTCHA_ID constant value.
     */
    public function testCaptchaIdConstant(): void
    {
        static::assertEquals('braintree', WebapiConfigProvider::CAPTCHA_ID);
    }
}

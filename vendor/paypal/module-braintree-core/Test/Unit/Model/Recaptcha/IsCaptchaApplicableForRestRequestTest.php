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
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use PayPal\Braintree\Model\Recaptcha\IsCaptchaApplicableForRestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IsCaptchaApplicableForRestRequestTest extends TestCase
{
    /**
     * @var Request|MockObject
     */
    private Request|MockObject $request;

    /**
     * @var EndpointInterface|MockObject
     */
    private EndpointInterface|MockObject $endpoint;

    /**
     * @var IsCaptchaApplicableForRestRequest
     */
    private IsCaptchaApplicableForRestRequest $isCaptchaApplicable;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->endpoint = $this->createMock(EndpointInterface::class);

        $this->isCaptchaApplicable = new IsCaptchaApplicableForRestRequest(
            $this->request
        );
    }

    /**
     * Test that captcha is applicable (returns true) for non-checkout service methods.
     *
     * Non-checkout endpoints should not be affected — request data is never inspected.
     */
    #[DataProvider('nonCheckoutServiceMethodDataProvider')]
    public function testExecuteReturnsTrueForNonCheckoutMethods(string $serviceMethod): void
    {
        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->request->expects(static::never())
            ->method('getRequestData');

        static::assertTrue($this->isCaptchaApplicable->execute($this->endpoint));
    }

    /**
     * @return array
     */
    public static function nonCheckoutServiceMethodDataProvider(): array
    {
        return [
            'random method' => ['serviceMethod' => 'someRandomMethod'],
            'get cart method' => ['serviceMethod' => 'getCart'],
            'add to cart method' => ['serviceMethod' => 'addToCart'],
        ];
    }

    /**
     * Test that captcha IS enforced only for the `braintree` payment method on checkout endpoints.
     *
     * ReCaptcha should only be applied when the payment method is explicitly `braintree`.
     */
    #[DataProvider('braintreePaymentDataProvider')]
    public function testExecuteReturnsTrueForBraintreePayment(string $serviceMethod): void
    {
        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->request->expects(static::once())
            ->method('getRequestData')
            ->willReturn([
                'paymentMethod' => [
                    'method' => 'braintree'
                ]
            ]);

        static::assertTrue($this->isCaptchaApplicable->execute($this->endpoint));
    }

    /**
     * @return array
     */
    public static function braintreePaymentDataProvider(): array
    {
        return [
            'braintree with savePaymentInformationAndPlaceOrder' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder'
            ],
            'braintree with placeOrder' => [
                'serviceMethod' => 'placeOrder'
            ]
        ];
    }

    /**
     * Test that captcha is skipped for Braintree sub-payment methods (Apple Pay, PayPal, Google Pay, etc.).
     *
     * These methods start with 'braintree' but are not the 'braintree' credit card method,
     * so ReCaptcha should be skipped for them.
     */
    #[DataProvider('braintreeSubPaymentMethodDataProvider')]
    public function testExecuteReturnsFalseForBraintreeSubPaymentMethods(
        string $serviceMethod,
        string $paymentMethod
    ): void {
        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->request->expects(static::once())
            ->method('getRequestData')
            ->willReturn([
                'paymentMethod' => [
                    'method' => $paymentMethod
                ]
            ]);

        static::assertFalse($this->isCaptchaApplicable->execute($this->endpoint));
    }

    /**
     * @return array
     */
    public static function braintreeSubPaymentMethodDataProvider(): array
    {
        return [
            'Apple Pay with savePaymentInformationAndPlaceOrder' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'paymentMethod' => 'braintree_applepay'
            ],
            'Apple Pay with placeOrder' => [
                'serviceMethod' => 'placeOrder',
                'paymentMethod' => 'braintree_applepay'
            ],
            'PayPal with savePaymentInformationAndPlaceOrder' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'paymentMethod' => 'braintree_paypal'
            ],
            'PayPal with placeOrder' => [
                'serviceMethod' => 'placeOrder',
                'paymentMethod' => 'braintree_paypal'
            ],
            'Google Pay with savePaymentInformationAndPlaceOrder' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'paymentMethod' => 'braintree_googlepay'
            ],
            'Google Pay with placeOrder' => [
                'serviceMethod' => 'placeOrder',
                'paymentMethod' => 'braintree_googlepay'
            ],
            'braintree_cc_vault with savePaymentInformationAndPlaceOrder' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'paymentMethod' => 'braintree_cc_vault'
            ]
        ];
    }

    /**
     * Test that captcha is NOT skipped for non-Braintree payment methods on checkout endpoints.
     *
     * Non-Braintree methods (checkmo, cashondelivery, etc.) must not be affected by
     * the Braintree plugin so that core place_order ReCaptcha continues to work for them.
     */
    #[DataProvider('nonBraintreePaymentMethodDataProvider')]
    public function testExecuteReturnsTrueForNonBraintreePaymentMethods(
        string $serviceMethod,
        string $paymentMethod
    ): void {
        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->request->expects(static::once())
            ->method('getRequestData')
            ->willReturn([
                'paymentMethod' => [
                    'method' => $paymentMethod
                ]
            ]);

        static::assertTrue($this->isCaptchaApplicable->execute($this->endpoint));
    }

    /**
     * @return array
     */
    public static function nonBraintreePaymentMethodDataProvider(): array
    {
        return [
            'checkmo with savePaymentInformationAndPlaceOrder' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'paymentMethod' => 'checkmo'
            ],
            'cashondelivery with placeOrder' => [
                'serviceMethod' => 'placeOrder',
                'paymentMethod' => 'cashondelivery'
            ]
        ];
    }

    /**
     * Test that captcha is NOT skipped when payment method is not set in request data.
     *
     * If the payment method is not present in the request, the Braintree plugin should
     * not interfere, allowing core ReCaptcha to work normally.
     */
    #[DataProvider('missingPaymentMethodDataProvider')]
    public function testExecuteReturnsTrueWhenPaymentMethodNotSet(
        string $serviceMethod,
        array $requestData
    ): void {
        $this->endpoint->method('getServiceMethod')
            ->willReturn($serviceMethod);

        $this->request->expects(static::once())
            ->method('getRequestData')
            ->willReturn($requestData);

        static::assertTrue($this->isCaptchaApplicable->execute($this->endpoint));
    }

    /**
     * @return array
     */
    public static function missingPaymentMethodDataProvider(): array
    {
        return [
            'empty request data with savePaymentInformationAndPlaceOrder' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'requestData' => []
            ],
            'empty request data with placeOrder' => [
                'serviceMethod' => 'placeOrder',
                'requestData' => []
            ],
            'missing method key' => [
                'serviceMethod' => 'savePaymentInformationAndPlaceOrder',
                'requestData' => [
                    'paymentMethod' => []
                ]
            ],
            'missing paymentMethod key' => [
                'serviceMethod' => 'placeOrder',
                'requestData' => [
                    'someOtherData' => 'value'
                ]
            ]
        ];
    }
}

<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
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

namespace PayPal\Braintree\Model\Adapter\PaymentMethod;

use Braintree\CreditCard;
use Braintree\PayPalAccount;
use Braintree\GooglePayCard;
use Magento\Framework\Exception\InvalidArgumentException;
use PayPal\Braintree\Model\Ui\ConfigProvider as BraintreeConfigProvider;
use PayPal\Braintree\Model\Ui\PayPal\ConfigProvider as BraintreePayPalConfigProvider;
use PayPal\Braintree\Model\GooglePay\Ui\ConfigProvider as BraintreeGooglePayConfigProvider;

class PaymentTokenAdapterFactory implements PaymentTokenAdapterFactoryInterface
{
    /**
     * @var BraintreePaymentTokenAdapterFactory
     */
    private BraintreePaymentTokenAdapterFactory $braintreePaymentTokenAdapterFactory;

    /**
     * @var BraintreePayPalPaymentTokenAdapterFactory
     */
    private BraintreePayPalPaymentTokenAdapterFactory $braintreePayPalPaymentTokenAdapterFactory;

    /**
     * @var BraintreeGooglePayPaymentTokenAdapterFactory
     */
    private BraintreeGooglePayPaymentTokenAdapterFactory $braintreeGooglePayPaymentTokenAdapterFactory;

    /**
     * @param BraintreePaymentTokenAdapterFactory $braintreePaymentTokenAdapterFactory
     * @param BraintreePayPalPaymentTokenAdapterFactory $braintreePayPalPaymentTokenAdapterFactory
     * @param BraintreeGooglePayPaymentTokenAdapterFactory $braintreeGooglePayPaymentTokenAdapterFactory
     */
    public function __construct(
        BraintreePaymentTokenAdapterFactory $braintreePaymentTokenAdapterFactory,
        BraintreePayPalPaymentTokenAdapterFactory $braintreePayPalPaymentTokenAdapterFactory,
        BraintreeGooglePayPaymentTokenAdapterFactory $braintreeGooglePayPaymentTokenAdapterFactory,
    ) {
        $this->braintreePaymentTokenAdapterFactory = $braintreePaymentTokenAdapterFactory;
        $this->braintreePayPalPaymentTokenAdapterFactory = $braintreePayPalPaymentTokenAdapterFactory;
        $this->braintreeGooglePayPaymentTokenAdapterFactory = $braintreeGooglePayPaymentTokenAdapterFactory;
    }

    /**
     * Create payment token adapter
     *
     * @param string $paymentMethodCode
     * @param CreditCard|PayPalAccount $paymentMethod
     * @return PaymentTokenAdapterInterface
     * @throws InvalidArgumentException
     */
    public function create(
        string $paymentMethodCode,
        CreditCard|PayPalAccount|GooglePayCard $paymentMethod
    ): PaymentTokenAdapterInterface {
        return match ($paymentMethodCode) {
            BraintreeConfigProvider::CODE => $this->braintreePaymentTokenAdapterFactory->create([
                'paymentMethod' => $paymentMethod
            ]),
            BraintreePayPalConfigProvider::PAYPAL_CODE => $this->braintreePayPalPaymentTokenAdapterFactory->create([
                'paymentMethod' => $paymentMethod
            ]),
            BraintreeGooglePayConfigProvider::METHOD_CODE => $this->braintreeGooglePayPaymentTokenAdapterFactory
                ->create([
                    'paymentMethod' => $paymentMethod
                ]),
            default => throw new InvalidArgumentException(
                __('There is no available Payment Token Adapter for %1', $paymentMethodCode)
            )
        };
    }
}

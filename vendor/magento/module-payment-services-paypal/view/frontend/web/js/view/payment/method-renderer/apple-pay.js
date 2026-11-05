/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'underscore',
    'mageUtils',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/apple-pay',
    'Magento_Checkout/js/model/payment/additional-validators',
], function (
    Component,
    $,
    _,
    utils,
    quote,
    fullScreenLoader,
    $t,
    ApplePayButton,
    additionalValidators,
) {
    'use strict';

    return Component.extend({
        defaults: {
            buttonContainerId: 'apple-pay-${ $.uid }',
            template: 'Magento_PaymentServicesPaypal/payment/apple-pay',
            isAvailable: false,
            isButtonRendered: false,
            paymentsOrderId: null,
            paymentSource: '',
            paymentTypeIconTitle: $t('Pay with Apple Pay'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            paymentTypeIconUrl: window.checkoutConfig.payment['payment_services_paypal_apple_pay'].paymentTypeIconUrl
        },

        /**
         * @inheritdoc
         */
        initialize: function (config) {
            config.uid = utils.uniqueid();
            this._super();
            this.initApplePayButton();
            return this;
        },

        /**
         * Initialize observables
         *
         * @returns {Component} Chainable.
         */
        initObservable: function () {
            this._super().observe('isAvailable isButtonRendered');
            return this;
        },

        /**
         * Create instance of smart buttons.
         */
        initApplePayButton: function () {
            const applePayConfig = window.checkoutConfig.payment[this.getCode()];

            const getApplePayRenderConfig = (defaultApplePayRenderConfig) =>
                _.extend({}, defaultApplePayRenderConfig, {
                    onSuccess: (event) => {
                        const promise = this.onPaymentSuccess(event)
                        promise.catch(defaultApplePayRenderConfig.onError)
                    },
                    getBillingAddress: this.getBillingAddress.bind(this),
                });

            const addErrorMessage = (message) => {
                this.messageContainer.addErrorMessage({ message });
            }

            this.applePayButton = new ApplePayButton({
                location: "CHECKOUT",
                placeOrderUrl: null, // not necessary as we override 'onSuccess'
                cancelUrl: null,
                maskedQuoteId: applePayConfig["maskedQuoteId"],
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: applePayConfig["paymentSdkParams"],
                getApplePayRenderConfig: getApplePayRenderConfig,
                validate: this.canProceedWithOrder.bind(this),
                showLoader: this.showFullScreenLoader.bind(this),
                isButtonDisabled: !quote.billingAddress(),
                logCustomerErrorMessage: addErrorMessage,
            });

            quote.billingAddress.subscribe((newAddress) => {
                this.applePayButton.isButtonDisabled(!newAddress);
            });
        },

        /**
         * Get method code
         *
         * @return {String}
         */
        getCode: function () {
            return 'payment_services_paypal_apple_pay';
        },

        /**
         * Get method data
         *
         * @return {Object}
         */
        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'payments_order_id': this.paymentsOrderId,
                    'payment_source': this.paymentSource,
                }
            };
        },

        /**
         * Render buttons
         */
        afterRender: function () {
            this.applePayButton.renderApplePayButton()
                .then(this.isAvailable.bind(this, true))
                .catch(() => {
                    this.isAvailable(false);
                    this.notEligibleErrorMessage = window.ApplePaySession?.canMakePayments()
                        ? this.notEligibleErrorMessage
                        : $t(`This device doesn't support creating Apple Pay payments.`);
                })
                .finally(this.isButtonRendered.bind(this, true));
        },

        /** Returns whether forms are valid and order can be placed. */
        canProceedWithOrder: function () {
            return this.validate()
                && additionalValidators.validate()
                && this.isPlaceOrderActionAllowed();
        },

        /** Shows/hides full-screen loader */
        showFullScreenLoader: function (show) {
            if (show) {
                fullScreenLoader.startLoader();
            } else {
                fullScreenLoader.stopLoader();
            }
        },

        /** Event handler for Apple Pay payment succeeded. */
        onPaymentSuccess: function ({ mpOrderId }) {
            this.paymentsOrderId = mpOrderId;
            this.paymentSource = "applepay"; // TODO(PAY-6482): Expose from SDK in SuccessEvent
            return this.placeOrderAndGetPromise();
        },

        /** Initiates order place action and returns promise that resolves when order is successfully placed. */
        placeOrderAndGetPromise: function () {
            if (!this.placeOrder()) {
                return Promise.reject(new Error("Place order not allowed."));
            }
            return new Promise((resolve, reject) => {
                this._placeOrderDeferredObject.done(resolve);
                this._placeOrderDeferredObject.fail(reject);
            });
        },

        /** Override to cache place order deferred object. */
        getPlaceOrderDeferredObject: function () {
            this._placeOrderDeferredObject = this._super();
            return this._placeOrderDeferredObject;
        },

        /** Returns quote billing address in format expected in 'getBillingAddress' render config callback. */
        getBillingAddress: function () {
            return {
                firstname: quote.billingAddress().firstname,
                lastname: quote.billingAddress().lastname,
                street: quote.billingAddress().street,
                region_id: quote.billingAddress().regionId,
                city: quote.billingAddress().city,
                postcode: quote.billingAddress().postcode,
                country_code: quote.billingAddress().countryId,
                telephone: quote.billingAddress().telephone,
            };
        },
    });
});

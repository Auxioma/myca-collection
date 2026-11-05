/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'Magento_Checkout/js/view/payment/default',
    'mageUtils',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/google-pay',
    'Magento_Checkout/js/model/payment/additional-validators',
], function (
    Component,
    utils,
    quote,
    fullScreenLoader,
    $t,
    GooglePayButton,
    additionalValidators,
) {
    'use strict';

    return Component.extend({
        defaults: {
            buttonContainerId: 'google-pay-${ $.uid }',
            template: 'Magento_PaymentServicesPaypal/payment/google-pay',
            isAvailable: true,
            isButtonRendered: false,
            paymentsOrderId: null,
            paymentSource: '',
            paymentTypeIconTitle: $t('Pay with Google Pay'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            paymentTypeIconUrl: window.checkoutConfig.payment['payment_services_paypal_google_pay'].paymentTypeIconUrl,
            location: window.checkoutConfig.payment['payment_services_paypal_google_pay'].location
        },

        /**
         * @inheritdoc
         */
        initialize: function (config) {
            config.uid = utils.uniqueid();
            this._super();
            this.initGooglePayButton();
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
        initGooglePayButton: function () {
            const paymentSdkParams = window.checkoutConfig.payment
                .payment_services_paypal_google_pay.paymentSdkParams;

            const addErrorMessage = (message) => {
                this.messageContainer.addErrorMessage({ message });
            };

            this.googlePayButton = new GooglePayButton({
                location: this.location,
                placeOrderUrl: null, // not necessary as we override 'onSuccess'
                reviewPageUrl: null,
                cancelUrl: null,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: paymentSdkParams,
                getGooglePayRenderConfig: (defaultGooglePayRenderConfig) => ({
                    ...defaultGooglePayRenderConfig,
                    getCartDetails: this.getCartDetails.bind(this),
                    onSuccess: this.onPaymentSuccess.bind(this),
                }),
                validate: this.canProceedWithOrder.bind(this),
                showLoader: this.showFullScreenLoader.bind(this),
                isButtonDisabled: !quote.billingAddress(),
                logCustomerErrorMessage: addErrorMessage,
            });

            quote.billingAddress.subscribe((newAddress) => {
                this.googlePayButton.isButtonDisabled(!newAddress);
            });
        },

        /**
         * Get method code
         *
         * @return {String}
         */
        getCode: function () {
            return 'payment_services_paypal_google_pay';
        },

        /** Get cart details */
        getCartDetails: function () {
            const totals = quote.getTotals()();
            const currencyCode = totals.base_currency_code;
            const isVirtual = quote.isVirtual();
            const billingAddress = this.getBillingAddress();

            const cartItems = quote.getItems().map(function (item) {
                return {
                    product: {
                        name: item.name
                    },
                    quantity: item.qty,
                    prices: {
                        row_total: {
                            currency: currencyCode,
                            value: parseFloat(item.base_row_total)
                        },
                    }
                };
            });

            const cartPrices = {
                grand_total: {
                    currency: currencyCode,
                    value: totals.base_grand_total
                },
                grand_total_excluding_tax: {
                    currency: currencyCode,
                    value: totals.base_grand_total - totals.base_tax_amount
                },
                subtotal_excluding_tax: {
                    currency: currencyCode,
                    value: totals.subtotal
                },
                subtotal_with_discount_excluding_tax: {
                    currency: currencyCode,
                    value: totals.subtotal_with_discount
                },
            };

            const cartDetails = {
                cartItems,
                cartPrices,
                billingAddress
            };

            if (!isVirtual) {
                const shippingMethod = quote.shippingMethod();

                cartDetails.shippingMethod = {
                    carrier_code: shippingMethod.carrier_code,
                    method_code: shippingMethod.method_code,
                    price_excl_tax: {
                        currency: currencyCode,
                        value: shippingMethod.price_excl_tax
                    }
                };
            }

            return cartDetails;
        },

        /**
         * Get data
         *
         * @returns {{method: *, additional_data: {payments_order_id: *, payment_source: *}}}
         */
        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'payments_order_id': this.paymentsOrderId,
                    'payment_source': this.paymentSource
                }
            };
        },

        /**
         * Render buttons
         */
        renderAfter: function () {
            this.googlePayButton.renderGooglePayButton()
                .then(this.isAvailable.bind(this, true))
                .catch(this.isAvailable.bind(this, false))
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

        /** Event handler for Google Pay payment succeeded. */
        onPaymentSuccess: function ({ mpOrderId }) {
            this.paymentsOrderId = mpOrderId;
            this.paymentSource = "googlepay"; // TODO(PAY-6482): Expose from SDK in SuccessEvent
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

        /** Returns quote billing address in format expected in 'getCartDetails' callback. */
        getBillingAddress: function () {
            return {
                firstname: quote.billingAddress().firstname,
                lastname: quote.billingAddress().lastname,
                street: quote.billingAddress().street,
                region: {
                    code: quote.billingAddress().regionCode,
                    label: quote.billingAddress().region,
                },
                city: quote.billingAddress().city,
                postcode: quote.billingAddress().postcode,
                country: {
                    code: quote.billingAddress().countryId,
                    label: quote.billingAddress().countryId,
                },
                telephone: quote.billingAddress().telephone,
                company: quote.billingAddress().company,
            };
        },
    });
});

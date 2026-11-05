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

/* eslint-disable no-undef */
define([
    'underscore',
    'uiComponent',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/apple-pay',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/model/messageList',
], function (_, Component, ApplePayButton, customerData, messageList) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment.payment_services_paypal_apple_pay, 'express', {});

    return Component.extend({
        defaults: {
            isAvailable: false,
            buttonContainerId: 'applepay-button-container',
            template: 'Magento_PaymentServicesPaypal/payment/apple-pay-express-checkout',
        },

        initialize: function () {
            this._super();
            this.initApplePayButton();
            return this;
        },

        initObservable: function () {
            this._super().observe('isAvailable');
            return this;
        },

        initApplePayButton: function () {
            const getApplePayRenderConfig = (defaultApplePayRenderConfig) =>
                _.extend({}, defaultApplePayRenderConfig, {
                    onCancel: this.getOnCancelHandler(defaultApplePayRenderConfig.onCancel),
                });

            this.applePayButton = new ApplePayButton({
                location: "START_OF_CHECKOUT",
                placeOrderUrl: config["placeOrderUrl"],
                maskedQuoteId: config["maskedQuoteId"],
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: config["paymentSdkParams"],
                logCustomerErrorMessage: (message) => messageList.addErrorMessage({ message }),
                getApplePayRenderConfig: getApplePayRenderConfig,
                onOrderPlaced: () => { customerData.invalidate(["cart"]) }
            });
            this.applePayButton.paymentSdkReady
                .then((sdk) => this.isAvailable(sdk.Payment.ApplePay.isAvailable()))
                .catch(console.error);
        },

        renderAfter: function () {
            this.applePayButton.renderApplePayButton().catch(console.error);
        },

        /** Extends default 'onCancel' handler to refresh the page. */
        getOnCancelHandler: function (defaultHandler) {
            return () => {
                defaultHandler();
                customerData.invalidate(['cart']);
                window.location.reload();
            };
        }
    });
});

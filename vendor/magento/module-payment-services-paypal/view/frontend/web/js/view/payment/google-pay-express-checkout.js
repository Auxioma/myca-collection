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
    'Magento_PaymentServicesPaypal/js/view/payment/methods/google-pay',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/model/messageList'
], function (_, Component, GooglePayButton, customerData, messageList) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment.payment_services_paypal_google_pay, 'express', {});

    return Component.extend({
        defaults: {
            isAvailable: false,
            buttonContainerId: 'google-button-container',
            template: 'Magento_PaymentServicesPaypal/payment/google-pay-express-checkout'
        },

        initialize: function () {
            this._super();
            this.initGooglePayButton();
            return this;
        },

        initObservable: function () {
            this._super().observe('isAvailable');
            return this;
        },

        initGooglePayButton: function () {
            const getGooglePayRenderConfig = (defaultGooglePayRenderConfig) =>
                _.extend({}, defaultGooglePayRenderConfig, {
                    onCancel: this.getOnCancelHandler(defaultGooglePayRenderConfig.onCancel),
                });

            this.googlePayButton = new GooglePayButton({
                location: config.location,
                placeOrderUrl: config.placeOrderUrl,
                reviewPageUrl: config.reviewPageUrl,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: config.paymentSdkParams,
                skipReviewPage: config.skipReviewStep,
                logCustomerErrorMessage: (message) => messageList.addErrorMessage({ message }),
                getGooglePayRenderConfig: getGooglePayRenderConfig,
                onOrderPlaced: () => { customerData.invalidate(["cart"]) }
            });
            this.googlePayButton.paymentSdkReady
                .then((sdk) => this.isAvailable(sdk.Payment.GooglePay.isAvailable()))
                .catch(console.error);
        },

        renderAfter: function () {
            this.googlePayButton.renderGooglePayButton().catch(console.error);
        },

        /** Extends default 'onCancel' handler to invalidate cart and refresh the page. */
        getOnCancelHandler: function (defaultHandler) {
            return () => {
                customerData.invalidate(['cart']);
                defaultHandler();
                window.location.reload();
            };
        }
    });
});

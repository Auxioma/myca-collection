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
    'mageUtils',
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'Magento_Customer/js/model/authentication-popup',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/google-pay'
], function (utils, Component, customerData, authPopup, GooglePayButton) {
    'use strict';

    return Component.extend({
        defaults: {
            pageType: '',
            placeOrderUrl: '',
            reviewPageUrl: '',
            cancelUrl: '',
            canSkipReviewForGooglePay: false,
            paymentSdkParams: {},
            buttonContainerId: 'google-pay-${ $.uid }',
        },

        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            config.uid = utils.uniqueid();
            this._super();
            this.element = element;
            this.element.id = this.buttonContainerId;
            this.initGooglePayButton();
            return this;
        },

        initGooglePayButton: function () {
            this.googlePayButton = new GooglePayButton({
                location: this.pageType.toUpperCase(),
                placeOrderUrl: this.placeOrderUrl,
                reviewPageUrl: this.reviewPageUrl,
                cancelUrl: this.cancelUrl,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: this.paymentSdkParams,
                skipReviewPage: this.canSkipReviewForGooglePay,
                validate: this.validateGuestCheckout.bind(this),
                onOrderPlaced: () => { customerData.invalidate(["cart"]) }
            });

            this.googlePayButton.renderGooglePayButton().catch(console.error);
        },

        validateGuestCheckout: function () {
            if (this.paymentSdkParams['isGuestCustomer']
                && !customerData.get('cart')().isGuestCheckoutAllowed
            ) {
                authPopup.showModal();
                return false;
            }
            return true;
        }
    });
});

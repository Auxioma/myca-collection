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
define([
    'underscore',
    'uiComponent',
    'mage/url',
    'domReady!'
], function (_, Component, url) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment, 'braintree_paypal_credit', {});

    return Component.extend({
        defaults: {
            template: 'PayPal_Braintree/express/express-paypal-credit',
            isActive: _.get(config, 'isActiveShipping', false),
            checkoutCurrency: window.checkoutConfig.quoteData.base_currency_code,
            checkoutAmount: window.checkoutConfig.quoteData.base_grand_total,
            checkoutLocale: _.get(config, 'locale', null),
            buttonLabel: _.get(config, ['style', 'label'], null),
            buttonColor: _.get(config, ['style', 'color'], null),
            buttonShape: _.get(config, ['style', 'shape'], null),
            skipOrderReviewStep: _.get(config, 'skipOrderReviewStep', true),
            actionSuccess: _.get(config, 'skipOrderReviewStep', true)
                ? url.build('checkout/onepage/success')
                : url.build('braintree/paypal/review'),
            storeCode: window.checkoutConfig.storeCode,
            quoteId: window.checkoutConfig.quoteData.entity_id,
            cspNonce: _.get(config, 'cspNonce', null),
            canSendCartLineItems: _.get(config, 'canSendCartLineItems', false),
            contactPreference: _.get(config, 'contactPreference', false)
        },

        /**
         * Initializes regular properties of instance.
         *
         * @returns {Object} Chainable.
         */
        initConfig: function () {
            this._super();

            return this;
        },

        /**
         * Is the payment method active.
         *
         * @return {boolean}
         */
        isMethodActive: function () {
            return this.isActive;
        },

        /**
         * Is Billing address required.
         *
         * @return {string}
         */
        getIsRequiredBillingAddress: function () {
            return _.get(config, 'isRequiredBillingAddress', '0') === '0' ? '' : 'true';
        },

        /**
         * Is Customer LoggedIn.
         *
         * @return {string}
         */
        getIsCustomerLoggedIn: function () {
            return _.get(window.checkoutConfig, 'isCustomerLoggedIn', false) === false ? '' : true;
        },

        /**
         * Get the merchant's name config.
         *
         * @return {string}
         */
        getMerchantName: function () {
            return _.get(config, 'merchantName', '');
        }
    });
});

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

/**
 * Express GooglePay button component
 */
define([
    'jquery',
    'underscore',
    'uiComponent',
    'mage/url',
    'PayPal_Braintree/js/googlepay/button',
    'PayPal_Braintree/js/googlepay/api',
    'domReady!'
], function ($, _, Component, url, button, buttonApi) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment, 'braintree_googlepay', {});

    return Component.extend({

        defaults: {
            template: 'PayPal_Braintree/express/express-googlepay',
            id: 'braintree-googlepay-express-payment',
            isActive: _.get(config, 'isActiveShipping', false),
            clientToken: _.get(config, 'clientToken', null),
            merchantId: _.get(config, 'merchantId', null),
            currencyCode: window.checkoutConfig.quoteData.base_currency_code,
            skipOrderReviewStep: _.get(config, 'skipOrderReviewStep', true),
            actionSuccess: _.get(config, 'skipOrderReviewStep', true)
                ? url.build('checkout/onepage/success')
                : url.build('braintree/googlepay/review'),
            amount: window.checkoutConfig.quoteData.base_grand_total,
            environment: _.get(config, 'environment', 'TEST'),
            cardTypes: _.get(config, 'cardTypes', []),
            btnColor: _.get(config, 'btnColor', ''),
            threeDSecure: null,
            storeCode: window.checkoutConfig.storeCode,
            multiCouponLimit: config.multiCouponLimit,
            quoteId: window.checkoutConfig.quoteId
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
         * Get the 3D Secure config object.
         *
         * @return {
        *   {
        *      thresholdAmount: (number|*),
        *      specificCountries: ([]|*),
        *      challengeRequested: (boolean|*),
        *      enabled: boolean
        *   } ||
        *   {
        *      thresholdAmount: number,
        *      specificCountries: *[],
        *      challengeRequested: boolean,
        *      enabled: boolean
        *   }
        * }
         */
        get3DSecureConfig: function () {
            let secureConfig = _.get(window.checkoutConfig.payment, 'three_d_secure', {});

            if (_.isEmpty(secureConfig)) {
                return {
                    'enabled': false,
                    'challengeRequested': false,
                    'thresholdAmount': 0.0,
                    'specificCountries': [],
                    'ipAddress': ''
                };
            }

            return {
                'enabled': secureConfig.enabled,
                'challengeRequested': secureConfig.challengeRequested,
                'thresholdAmount': secureConfig.thresholdAmount,
                'specificCountries': secureConfig.specificCountries,
                'ipAddress': secureConfig.ipAddress
            };
        },

        /**
         * Initialize Google Pay express.
         */
        initGooglePayExpress: function () {
            if (!this.isMethodActive()) {
                return;
            }

            this.threeDSecure = this.get3DSecureConfig();

            /* Add client token & environment to 3DS Config */
            this.threeDSecure.clientToken = this.clientToken;
            this.threeDSecure.environment = this.environment;

            const element = $(`#${this.id}`);
            let api = new buttonApi();

            api.setEnvironment(this.environment);
            api.setCurrencyCode(this.currencyCode);
            api.setClientToken(this.clientToken);
            api.setMerchantId(this.merchantId);
            api.setActionSuccess(this.actionSuccess);
            api.setAmount(this.amount);
            api.setCardTypes(this.cardTypes);
            api.setBtnColor(this.btnColor);
            api.setThreeDSecureValidatorConfig(this.threeDSecure);
            api.setStoreCode(this.storeCode);
            api.setQuoteId(this.quoteId);
            api.setSkipReview(this.skipOrderReviewStep);
            api.setElement(element);
            api.setMultiCouponLimit(this.multiCouponLimit);

            // Attach the button
            button.init(
                element,
                api
            );
        },

        /**
         * @returns {Object}
         */
        initialize: function () {
            this._super();

            return this;
        }
    });
});

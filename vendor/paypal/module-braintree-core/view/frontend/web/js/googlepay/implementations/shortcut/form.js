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
/*browser:true*/
define([
    'underscore',
    'uiComponent'
], function (_, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            braintreeClient: null,
            code: 'braintree',
            isProcessing: false,

            /**
             * Braintree client configuration
             *
             * {Object}
             */
            clientConfig: {
                onReady: function () {},

                /**
                 * Triggers on payment nonce receive
                 */
                onPaymentMethodReceived: function () {
                    this.isProcessing = false;
                },

                /**
                 * Allow a new nonce to be generated
                 */
                onPaymentMethodError: function () {
                    this.isProcessing = false;
                },

                /**
                 * After Braintree instance initialization
                 */
                onInstanceReady: function () {},

                /**
                 * Triggers on any Braintree error
                 * @param {Object} response
                 */
                onError: function (response) {
                    this.isProcessing = false;
                    throw response.message;
                },

                /**
                 * Triggers when customer click "Cancel"
                 */
                onCancelled: function () {
                    this.isProcessing = false;
                }
            }
        },

        /**
         * Set list of observable attributes
         *
         * @returns {exports.initObservable}
         */
        initObservable: function () {
            this._super();

            this.initClientConfig();

            return this;
        },

        /**
         * Get payment name
         *
         * @returns {String}
         */
        getCode: function () {
            return this.code;
        },

        /**
         * Init config
         */
        initClientConfig: function () {
            _.each(this.clientConfig, function (fn, name) {
                if (typeof fn === 'function') {
                    this.clientConfig[name] = fn.bind(this);
                }
            }, this);
        }
    });
});

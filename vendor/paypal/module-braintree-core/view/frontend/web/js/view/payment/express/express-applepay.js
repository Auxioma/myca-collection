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
 * Braintree Apple Pay express payment method integration.
 **/
define([
    'underscore',
    'uiComponent',
    'PayPal_Braintree/js/applepay/button',
    'PayPal_Braintree/js/applepay/api',
    'PayPal_Braintree/js/helper/format-amount',
    'mage/translate',
    'mage/url',
    'domReady!'
], function (
    _,
    Component,
    button,
    buttonApi,
    formatAmount,
    $t,
    url
) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment, 'braintree_applepay', {});

    return Component.extend({

        defaults: {
            template: 'PayPal_Braintree/express/express-applepay',
            id: 'braintree-applepay-express-payment',
            isActive: _.get(config, 'isActiveShipping', false),
            clientToken: _.get(config, 'clientToken', null),
            quoteId: window.checkoutConfig.quoteId,
            displayName: _.get(config, 'merchantName', null),
            actionSuccess: url.build('checkout/onepage/success'),
            grandTotalAmount: window.checkoutConfig.quoteData.base_grand_total,
            storeCode: window.checkoutConfig.storeCode,
            priceIncludesTax: _.get(config, 'priceIncludesTax', true),
            currencyCode: window.checkoutConfig.quoteData.base_currency_code
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
         * Initialize Apple Pay express.
         */
        initApplePayExpress: function () {
            if (!this.isMethodActive() || !this.clientToken) {
                return;
            }

            if (!this.displayName) {
                this.displayName = $t('Store');
            }

            let api = new buttonApi();

            api.setGrandTotalAmount(formatAmount(this.grandTotalAmount));
            api.setClientToken(this.clientToken);
            api.setDisplayName(this.displayName);
            api.setQuoteId(this.quoteId);
            api.setActionSuccess(this.actionSuccess);
            api.setStoreCode(this.storeCode);
            api.setPriceIncludesTax(this.priceIncludesTax);
            api.setCurrencyCode(this.currencyCode);

            // Attach the button
            button.init(
                document.getElementById(this.id),
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

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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
 * Braintree Google Pay mini cart payment method integration.
 **/
define(
    [
        'uiComponent',
        'jquery',
        'Magento_Customer/js/customer-data',
        'PayPal_Braintree/js/googlepay/button',
        'PayPal_Braintree/js/googlepay/api',
        'domReady!'
    ],
    function (
        Component,
        $,
        customerData,
        button,
        buttonApi
    ) {
        'use strict';

        return Component.extend({

            defaults: {
                id: null,
                clientToken: null,
                merchantId: null,
                currencyCode: null,
                actionSuccess: null,
                amount: null,
                environment: 'TEST',
                cardType: [],
                btnColor: 0,
                threeDSecure: null,
                quoteId: 0,
                storeCode: 'default',
                skipOrderReviewStep: false,
                priceFormat: [],
            },

            /**
             * @returns {Object}
             */
            initialize: function () {
                this._super();

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
                api.setSkipReview(this.skipOrderReviewStep);
                api.setPriceIncludesTax(this.priceIncludesTax);
                api.setElement(element);
                api.setPriceFormat(this.priceFormat);
                api.setMultiCouponLimit(this.multiCouponLimit);

                const cart = customerData.get('cart');

                cart.subscribe(({ braintree_masked_id }) => {
                    api.setQuoteId(braintree_masked_id);
                });

                if (cart()?.braintree_masked_id) {
                    api.setQuoteId(cart().braintree_masked_id);
                }

                // Attach the button
                button.init(
                    element,
                    api
                );

                return this;
            }
        });
    }
);

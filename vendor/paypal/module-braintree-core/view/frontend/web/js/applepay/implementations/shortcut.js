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
 * Braintree Apple Pay mini cart payment method integration.
 **/
define(
    [
        'uiComponent',
        'Magento_Customer/js/customer-data',
        'PayPal_Braintree/js/applepay/button',
        'PayPal_Braintree/js/applepay/api',
        'mage/translate',
        'domReady!'
    ],
    function (
        Component,
        customerData,
        button,
        buttonApi,
        $t
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                id: null,
                clientToken: null,
                quoteId: 0,
                displayName: null,
                actionSuccess: null,
                grandTotalAmount: 0,
                storeCode: 'default'
            },

            /**
             * @returns {Object}
             */
            initialize: function () {
                this._super();
                if (!this.displayName) {
                    this.displayName = $t('Store');
                }

                let api = new buttonApi();

                api.setGrandTotalAmount(parseFloat(this.grandTotalAmount).toFixed(2));
                api.setCurrencyCode(this.currencyCode);
                api.setClientToken(this.clientToken);
                api.setDisplayName(this.displayName);
                api.setActionSuccess(this.actionSuccess);
                api.setStoreCode(this.storeCode);
                api.setPriceIncludesTax(this.priceIncludesTax);

                const cart = customerData.get('cart');

                cart.subscribe(({ braintree_masked_id }) => {
                    api.setQuoteId(braintree_masked_id);
                });

                if (cart()?.braintree_masked_id) {
                    api.setQuoteId(cart().braintree_masked_id);
                }

                // Attach the button
                button.init(
                    document.getElementById(this.id),
                    api
                );

                return this;
            }
        });
    }
);

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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
 * Braintree Apple Pay product page express integration.
 **/
define(
    [
        'jquery',
        'uiComponent',
        'Magento_Customer/js/customer-data',
        'PayPal_Braintree/js/applepay/button',
        'PayPal_Braintree/js/applepay/api',
        'PayPal_Braintree/js/helper/add-product-to-cart',
        'mage/translate',
        'domReady!'
    ],
    function (
        $,
        Component,
        customerData,
        button,
        buttonApi,
        addProductToCart,
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

                api.onButtonClick = (session) => {
                    addProductToCart(api)
                        .then(() => {
                            $('body').loader('hide');
                            session.begin();
                        })
                        .catch(() => {
                            $('body').loader('hide');
                        });
                }

                api.setGrandTotalAmount(parseFloat(this.grandTotalAmount).toFixed(2));
                api.setCurrencyCode(this.currencyCode);
                api.setClientToken(this.clientToken);
                api.setDisplayName(this.displayName);
                api.setActionSuccess(this.actionSuccess);
                api.setStoreCode(this.storeCode);
                api.setPriceIncludesTax(this.priceIncludesTax);
                api.setIsPdp(true);
                api.setIsVirtual(this.isVirtual);

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

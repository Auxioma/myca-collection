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
 * Braintree Google Pay mini cart payment method integration.
 **/
define(
    [
        'uiComponent',
        'jquery',
        'Magento_Customer/js/customer-data',
        'PayPal_Braintree/js/googlepay/button',
        'PayPal_Braintree/js/googlepay/api',
        'PayPal_Braintree/js/googlepay/model/parsed-response',
        'PayPal_Braintree/js/helper/add-product-to-cart',
        'domReady!'
    ],
    function (
        Component,
        $,
        customerData,
        button,
        buttonApi,
        parsedResponseModel,
        addProductToCart
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
                productFormSelector: '#product_addtocart_form',
                productAddedToCart: false,
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

                api.onButtonClick = (paymentsClient, googlePaymentInstance) => {
                    addProductToCart(api)
                        .then(async() => {
                            const paymentData = await api.getPaymentRequest();
                            let paymentDataRequest = googlePaymentInstance.createPaymentDataRequest(paymentData);

                            paymentsClient.loadPaymentData(paymentDataRequest).catch(function (err) {
                                // Handle errors
                                // err = {statusCode: "CANCELED"}
                                console.error(err);
                                parsedResponseModel.resetDefaultData();
                                $('body').loader('hide');
                            });
                        })
                        .catch(() => {
                            $('body').loader('hide');
                        });
                }

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
                    element,
                    api
                );

                return this;
            }
        });
    }
);

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
define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'PayPal_Braintree/js/helper/check-guest-checkout',
    'PayPal_Braintree/js/paypal/button',
    'mage/url'
], function ($, customerData, checkGuestCheckout, button, url) {
    'use strict';

    return button.extend({
        defaults: {
            branding: true,
            label: 'buynow',
            productFormSelector: '#product_addtocart_form',
            productAddedToCart: false,
            addToCartPromise: null
        },

        /**
         * Create Order event to send create payment request to PayPal
         *
         * @param paypalCheckoutInstance
         * @param currentElement
         * @returns {*}
         */
        createOrder: function (paypalCheckoutInstance, currentElement) {
            return this.addToCartPromise.then((cartData) => {
                if (!checkGuestCheckout()) {
                    return false;
                }

                const {cartId, lineItems, amountBreakdown, amount} = JSON.parse(cartData);

                let createPaymentData = {
                    amount,
                    locale: currentElement.data('locale'),
                    currency: currentElement.data('currency'),
                    flow: 'checkout',
                    enableShippingAddress: true,
                    displayName: currentElement.data('displayname')
                };

                this.setQuoteId(cartId);

                // if 'Skip Order Review Page' config is set to YES then only pass SSSC parameters
                if (this.getSkipReview()) {
                    createPaymentData.shippingCallbackUrl = url.build('braintree/shipping/callback?cart_id=' + cartId);
                    createPaymentData.callbackEvents = ['SHIPPING_ADDRESS', 'SHIPPING_OPTIONS'];
                }

                // If 'Send Cart Line Items for PayPal' config is set YES then only pass line items parameters
                if (this.canSendCartLineItems(this.buttonConfig)) {
                    createPaymentData.lineItems = lineItems;
                    createPaymentData.amountBreakdown = amountBreakdown;
                }

                createPaymentData.contactPreference = 'NO_CONTACT_INFO';
                if (this.getContactPreference(this.buttonConfig)) {
                    createPaymentData.contactPreference = 'UPDATE_CONTACT_INFO';
                }

                return paypalCheckoutInstance.createPayment(createPaymentData);
            });
        },

        /**
         * On click add the current product to the quote and proceed with PayPal checkout.
         */
        onClick: function (data, actions) {
            const isAllowed = this._super();

            if (!isAllowed) {
                return actions.reject();
            }

            let $form = $(this.productFormSelector);

            if (!this.productAddedToCart) {
                $form.trigger('submit');

                if ($form.validation('isValid')) {
                    $('body').trigger('processStart');

                    this.addToCartPromise = fetch(`/rest/${this.getStoreCode()}/V1/paypal/oneClick`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'x-requested-with': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            method: this.getCode(),
                            productData: JSON.stringify($form.serializeArray())
                        })
                    }).then((response) => response.json());

                    return;
                }

                return actions.reject();
            }

            return actions.resolve();
        }
    });
});

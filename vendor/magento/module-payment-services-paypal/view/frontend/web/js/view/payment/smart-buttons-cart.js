/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2021 Adobe
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
    'require',
    'mageUtils',
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'Magento_Customer/js/model/authentication-popup',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/smart-buttons'
], function (require, utils, Component, customerData, authPopup, SmartButtons) {
    'use strict';

    return Component.extend({
        defaults: {
            syncQuoteUrl: '',
            restoreQuoteUrl: '',
            cancelUrl: '',
            canSkipReviewForPayPal: false,
            pageType: '',
            placeOrderUrl: '',
            paymentSdkParams: {},
            buttonContainerId: 'smart-buttons-${ $.uid }'
        },

        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            config.uid = utils.uniqueid();
            this._super();
            this.isComponentDestroyed = false;
            this.element = element;
            this.element.id = this.buttonContainerId;
            this.initSmartButtons();
            return this;
        },

        initSmartButtons: function () {
            this.smartButtons = new SmartButtons({
                cancelUrl: this.cancelUrl,
                location: this.pageType.toUpperCase(),
                placeOrderUrl: this.placeOrderUrl,
                syncQuoteUrl: this.syncQuoteUrl,
                restoreQuoteUrl: this.restoreQuoteUrl,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: this.paymentSdkParams,
                canSkipReviewForPayPal: this.canSkipReviewForPayPal,
                validate: this.validateGuestCheckout.bind(this),
                onOrderPlaced: function () {
                    customerData.invalidate(['cart']);
                }
            });

            this.observePayLaterMessagePrice();
            this.smartButtons.renderSmartButtons().catch(console.error);
        },

        observePayLaterMessagePrice: function () {
            if (this.pageType === 'cart') {
                require(['Magento_Checkout/js/model/quote'], function (quote) {
                    if (this.isComponentDestroyed) {
                        return;
                    }

                    this.priceSubscription = quote.totals.subscribe(this.onCartTotalsChanged, this);
                    this.onCartTotalsChanged(quote.totals());
                }.bind(this));
                return;
            }

            const cart = customerData.get('cart'),
                initialCart = cart(),
                hasItems = initialCart && Number(initialCart.summary_count) > 0,
                isPriceMissing = initialCart &&
                    (initialCart.base_subtotal === undefined || initialCart.base_currency_code === undefined);

            this.priceSubscription = cart.subscribe(this.onMiniCartChanged, this);
            this.onMiniCartChanged(initialCart);

            if (hasItems && isPriceMissing) {
                customerData.reload(['cart'], true);
            }
        },

        onCartTotalsChanged: function (totals) {
            this.smartButtons.updatePayLaterMessagePrice(totals ? {
                currency: totals.base_currency_code,
                value: Number(totals.base_grand_total)
            } : null);
        },

        onMiniCartChanged: function (cart) {
            const hasItems = cart && Number(cart.summary_count) > 0;

            this.smartButtons.updatePayLaterMessagePrice(hasItems ? {
                currency: cart.base_currency_code,
                value: Number(cart.base_subtotal)
            } : null);
        },

        /** @inheritdoc */
        destroy: function () {
            this.isComponentDestroyed = true;
            if (this.priceSubscription) {
                this.priceSubscription.dispose();
                this.priceSubscription = null;
            }
            if (this.smartButtons) {
                this.smartButtons.disposePayLaterMessage();
            }

            return this._super();
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

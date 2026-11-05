/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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
    'Magento_PaymentServicesPaypal/js/view/payment/methods/smart-buttons',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/model/messageList'
], function (_, Component, SmartButtons, quote, customerData, messageList) {
    'use strict';

    const config = _.get(window.checkoutConfig.payment.payment_services_paypal_smart_buttons, 'express', {});

    return Component.extend({
        defaults: {
            syncQuoteUrl: config.syncQuoteUrl,
            restoreQuoteUrl: config.restoreQuoteUrl,
            canSkipReviewForPayPal: config.canSkipReviewForPayPal,
            isAvailable: false,
            buttonContainerId: 'paypal-express-container-paypal',
            template: 'Magento_PaymentServicesPaypal/payment/smart-buttons-express-checkout'
        },

        initialize: function () {
            this._super();
            this.initSmartButtons();
            return this;
        },

        initObservable: function () {
            this._super().observe('isAvailable');
            return this;
        },

        initSmartButtons: function () {
            this.smartButtons = new SmartButtons({
                location: 'START_OF_CHECKOUT',
                placeOrderUrl: config.placeOrderUrl,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: config.paymentSdkParams,
                canSkipReviewForPayPal: this.canSkipReviewForPayPal,
                syncQuoteUrl: this.syncQuoteUrl,
                restoreQuoteUrl: this.restoreQuoteUrl,
                onOrderPlaced: () => customerData.invalidate(['cart']),
                logCustomerErrorMessage: (message) => messageList.addErrorMessage({ message })
            });

            if (this.totalsSubscription) {
                this.totalsSubscription.dispose();
            }
            if (this.shippingMethodSubscription) {
                this.shippingMethodSubscription.dispose();
            }
            this.totalsSubscription = quote.totals.subscribe(this.onTotalsChanged, this);
            this.shippingMethodSubscription = quote.shippingMethod.subscribe(this.onShippingMethodChanged, this);
            this.onTotalsChanged(quote.totals());
            this.onShippingMethodChanged(quote.shippingMethod());

            this.smartButtons.paymentSdkReady
                .then((sdk) => this.isAvailable(sdk.Payment.PayPalButtons.isAvailable()))
                .catch(console.error);
        },

        renderAfter: function () {
            this.smartButtons.renderSmartButtons().catch(console.error);
        },

        onTotalsChanged: function (totals) {
            this.totals = totals;
            this.updatePayLaterMessagePrice();
        },

        onShippingMethodChanged: function (shippingMethod) {
            this.shippingMethod = shippingMethod;
            this.updatePayLaterMessagePrice();
        },

        updatePayLaterMessagePrice: function () {
            const totals = this.totals;

            if (!totals) {
                this.smartButtons.updatePayLaterMessagePrice(null);
                return;
            }

            const baseGrandTotal = Number(totals.base_grand_total),
                selectedShippingAmount = this.getSelectedBaseShippingAmount(),
                appliedShippingAmount = this.getAppliedBaseShippingAmount(),
                updatedGrandTotal = baseGrandTotal - appliedShippingAmount + selectedShippingAmount;

            this.smartButtons.updatePayLaterMessagePrice({
                currency: totals.base_currency_code,
                value: Number.isFinite(selectedShippingAmount) &&
                    Number.isFinite(appliedShippingAmount) &&
                    updatedGrandTotal >= 0
                    ? updatedGrandTotal
                    : baseGrandTotal
            });
        },

        getSelectedBaseShippingAmount: function () {
            const shippingMethod = this.shippingMethod;

            if (!shippingMethod) {
                return null;
            }

            return this.getFiniteAmount(shippingMethod.base_amount);
        },

        getAppliedBaseShippingAmount: function () {
            const totals = this.totals;

            return this.getFiniteAmount(totals.base_shipping_amount);
        },

        getFiniteAmount: function () {
            let value;

            for (let index = 0; index < arguments.length; index++) {
                if (arguments[index] === null || arguments[index] === undefined || arguments[index] === '') {
                    continue;
                }
                value = Number(arguments[index]);
                if (Number.isFinite(value)) {
                    return value;
                }
            }

            return null;
        },

        /** @inheritdoc */
        destroy: function () {
            if (this.totalsSubscription) {
                this.totalsSubscription.dispose();
                this.totalsSubscription = null;
            }
            if (this.shippingMethodSubscription) {
                this.shippingMethodSubscription.dispose();
                this.shippingMethodSubscription = null;
            }
            if (this.smartButtons) {
                this.smartButtons.disposePayLaterMessage();
            }

            return this._super();
        }
    });
});

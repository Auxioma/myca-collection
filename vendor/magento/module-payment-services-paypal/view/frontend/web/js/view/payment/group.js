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
    'Magento_Checkout/js/model/quote'
], function (_, Component, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Magento_PaymentServicesPaypal/payment/group'
        },

        getShowExpress: function () {
            const totals = quote.getTotals()(),
                grandTotal = totals ? totals.base_grand_total : 0;

            return grandTotal > 0 && this.expressMethodEnabled();
        },

        expressMethodEnabled: function () {
            const methods = [
                'payment_services_paypal_smart_buttons',
                'payment_services_paypal_google_pay',
                'payment_services_paypal_apple_pay'
            ];

            return methods.some((method) => {
                return !!_.get(window.checkoutConfig.payment[method], 'express', false);
            });
        },

        sortMethods: function (a, b) {
            return parseInt(a.sort, 10) - parseInt(b.sort, 10);
        }
    });
});

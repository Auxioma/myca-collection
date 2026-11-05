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
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    let config = window.checkoutConfig.payment;

    if (config['braintree_googlepay'].clientToken) {
        rendererList.push({
            type: 'braintree_googlepay',
            component: 'PayPal_Braintree/js/googlepay/implementations/core-checkout/method-renderer/googlepay'
        });
    }

    return Component.extend({});
});

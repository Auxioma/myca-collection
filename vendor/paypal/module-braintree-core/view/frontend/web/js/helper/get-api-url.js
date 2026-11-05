/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
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
    'PayPal_Braintree/js/helper/is-logged-in'
], function (isLoggedIn) {
    'use strict';

    return function (uri, storeCode, quoteId) {
        if (isLoggedIn()) {
            return '/rest/' + storeCode + '/V1/carts/mine/' + uri;
        }

        return '/rest/' + storeCode + '/V1/guest-carts/' + quoteId + '/' + uri;
    };
});

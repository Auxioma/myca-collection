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

define(function () {
    'use strict';

    /**
     * Maps Payment Services payment location (see PaymentLocation enum in GraphQL schema) to the
     * storefront page type where payment methods for that location are normally rendered.
     */
    return (location) => {
        switch (location) {
            case "PRODUCT_DETAIL": return "product"
            case "MINICART": return "minicart"
            case "CART": return "cart"
            case "CHECKOUT":
            case "START_OF_CHECKOUT":
            case "ADMIN":
                return undefined
            default:
                throw new Error(`Unknown location: '${location}'.`)
        }
    };
});

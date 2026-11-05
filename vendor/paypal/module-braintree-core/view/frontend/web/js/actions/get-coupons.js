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
define([
    'PayPal_Braintree/js/actions/graphql-request'
], function (graphQlRequest) {
    'use strict';

    return async function (storeCode, quoteId) {
        const request = `{
            cart(cart_id: "${quoteId}") {
                applied_coupons {
                    code
                }
                prices {
                    discounts {
                        coupon {
                            code
                        }
                        amount {
                            value
                        }
                        label
                    }
                }
            }
        }`;

        return graphQlRequest(request, {}, storeCode)
            .then((response) => {
                if (response.errors) {
                    throw new Error(response.errors[0].message, { cause: response.errors[0].path });
                }

                return response.data.cart;
            });
    };
});

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

    return function (method, coupon, couponLimit, storeCode, quoteId) {
        if (couponLimit > 1) {
            method = method === 'applyCouponToCart'
                ? 'applyCouponsToCart'
                : 'removeCouponsFromCart';
        }

        const request = `
            mutation {
                ${method}(
                    input: {
                        cart_id: "${quoteId}"
                        ${method === 'applyCouponToCart' ? `coupon_code: "${coupon}"` : ''}
                        ${
                            method === 'applyCouponsToCart'
                                ? `
                                    coupon_codes: ["${coupon}"]
                                    type: APPEND
                                `: ''
                        }
                        ${
                            method === 'removeCouponsFromCart'
                                ? `coupon_codes: ["${coupon}"]`
                                : ''
                        }
                    }
                ) {
                    cart {
                        id
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
                }
            }`;

        return graphQlRequest(request, {}, storeCode).then((response) => {
            if (response.errors) {
                throw new Error(response.errors[0].message, { cause: response.errors[0].path });
            }

            return response.data[method].cart;
        });
    };
});

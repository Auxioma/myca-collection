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
    'mage/translate',
], function ($t) {
    'use strict';

    return function mapPaymentSdkErrorMessage(error) {
        if (!error?.code) {
            return null;
        }

        const messages = {
            APPLE_PAY_ZERO_AMOUNT: $t(
                'Apple Pay is not available when the order total is 0. Please choose a different payment method.'
            ),
            GOOGLE_PAY_ZERO_AMOUNT: $t(
                'Google Pay is not available when the order total is 0. Please choose a different payment method.'
            ),
            GOOGLE_PAY_THREE_DS_FAILED: $t(
                "Unable to validate payment with 3DS. Please choose a different payment method."
            ),
        };

        return messages[error.code] || null;
    };
});

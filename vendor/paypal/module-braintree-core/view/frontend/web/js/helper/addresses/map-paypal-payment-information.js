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
    'underscore',
    'PayPal_Braintree/js/helper/replace-single-quote-character',
    'PayPal_Braintree/js/helper/remove-non-digit-characters',
    'PayPal_Braintree/js/model/region-data'
], function (_, replaceSingleQuoteCharacter, removeNonDigitCharacters, regionDataModel) {
    'use strict';

    return function (payload, isRequiredBillingAddress, contactPreference) {
        const billingAddress = isRequiredBillingAddress && payload.details?.billingAddress?.line1
                ? payload.details.billingAddress
                : payload.details.shippingAddress,
            shippingAddress = payload.details.shippingAddress;

        let recipientFirstName,
            recipientLastName,
            telephone,
            emailAddress;

        // get recipient first and last name
        if (typeof billingAddress.recipientName !== 'undefined') {
            let [
                splitRecipientFirstName,
                ...splitRecipientLastName
            ] = billingAddress.recipientName.split(' ');

            recipientFirstName = replaceSingleQuoteCharacter(splitRecipientFirstName);
            recipientLastName = splitRecipientLastName.map(replaceSingleQuoteCharacter).join(' ');
        } else {
            recipientFirstName = replaceSingleQuoteCharacter(payload.details.firstName);
            recipientLastName = replaceSingleQuoteCharacter(payload.details.lastName);
        }

        // PayPal - Contact module preference
        if (contactPreference) {
            telephone = typeof shippingAddress.phone !== 'undefined' ? shippingAddress.phone : '0000000000';
            emailAddress = typeof shippingAddress.recipientEmail !== 'undefined'
                ? shippingAddress.recipientEmail
                : payload.details.email;
        } else {
            telephone = typeof payload.details.phone !== 'undefined' ? payload.details.phone : '0000000000';
            emailAddress = payload.details.email;
        }

        return {
            'email': replaceSingleQuoteCharacter(emailAddress),
            'paymentMethod': {
                'method': 'braintree_paypal',
                'additional_data': {
                    'payment_method_nonce': payload.nonce
                }
            },
            'billing_address': {
                'email': replaceSingleQuoteCharacter(emailAddress),
                'telephone': removeNonDigitCharacters(telephone),
                'firstname': recipientFirstName,
                'lastname': recipientLastName,
                'street': typeof billingAddress.line2 !== 'undefined' && _.isString(billingAddress.line2)
                    ? [
                        replaceSingleQuoteCharacter(billingAddress.line1),
                        replaceSingleQuoteCharacter(billingAddress.line2)
                    ]
                    : [replaceSingleQuoteCharacter(billingAddress.line1)],
                'city': replaceSingleQuoteCharacter(billingAddress.city),
                'region': typeof billingAddress.state !== 'undefined'
                    ? replaceSingleQuoteCharacter(billingAddress.state)
                    : '',
                'region_id': regionDataModel.getRegionIdByCode(
                    billingAddress.countryCode,
                    billingAddress?.state?.replace(/'/g, '&apos;') || ''
                ),
                'region_code': null,
                'country_id': billingAddress.countryCode,
                'postcode': billingAddress.postalCode,
                'same_as_billing': 0,
                'customer_address_id': 0,
                'save_in_address_book': 0
            }
        };
    };
});

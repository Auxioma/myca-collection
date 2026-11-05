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
], function (_, replaceSingleQuoteCharacter, removeNonDigitCharacters,regionDataModel) {
    'use strict';

    return function (payload, shippingMethod, contactPreference) {
        let address = payload.details.shippingAddress,
            recipientFirstName,
            recipientLastName,
            telephone,
            emailAddress;

        // get recipient first and last name
        if (typeof address.recipientName !== 'undefined') {
            let [
                splitRecipientFirstName,
                ...splitRecipientLastName
            ] = address.recipientName.split(' ');

            recipientFirstName = replaceSingleQuoteCharacter(splitRecipientFirstName);
            recipientLastName = splitRecipientLastName.map(replaceSingleQuoteCharacter).join(' ');
        } else {
            recipientFirstName = replaceSingleQuoteCharacter(payload.details.firstName);
            recipientLastName = replaceSingleQuoteCharacter(payload.details.lastName);
        }

        // PayPal - Contact module preference
        if (contactPreference) {
            telephone = typeof address.phone !== 'undefined' ? address.phone : '0000000000';
            emailAddress = typeof address.recipientEmail !== 'undefined'
                ? address.recipientEmail
                : payload.details.email;
        } else {
            telephone = typeof payload.details.phone !== 'undefined' ? payload.details.phone : '0000000000';
            emailAddress = payload.details.email;
        }

        return {
            addressInformation: {
                'shipping_method_code': shippingMethod.method_code,
                'shipping_carrier_code': shippingMethod.carrier_code,
                'shipping_address': {
                    'email': replaceSingleQuoteCharacter(emailAddress),
                    'telephone': removeNonDigitCharacters(telephone),
                    'firstname': recipientFirstName,
                    'lastname': recipientLastName,
                    'street': typeof address.line2 !== 'undefined' && _.isString(address.line2)
                        ? [
                            replaceSingleQuoteCharacter(address.line1),
                            replaceSingleQuoteCharacter(address.line2)
                        ]
                        : [replaceSingleQuoteCharacter(address.line1)],
                    'city': replaceSingleQuoteCharacter(address.city),
                    'region': typeof address.state !== 'undefined'
                        ? replaceSingleQuoteCharacter(address.state)
                        : '',
                    'region_id': regionDataModel.getRegionIdByCode(
                        address.countryCode,
                        address?.state?.replace(/'/g, '&apos;') || ''
                    ),
                    'region_code': null,
                    'country_id': address.countryCode,
                    'postcode': address.postalCode,
                    'same_as_billing': 0,
                    'customer_address_id': 0,
                    'save_in_address_book': 0
                }
            }
        };
    };
});

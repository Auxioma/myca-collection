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

/**
 * Braintree Apple Pay button API
 **/
define(
    [
        'jquery',
        'underscore',
        'uiComponent',
        'mage/translate',
        'Magento_Customer/js/customer-data',
        'PayPal_Braintree/js/actions/create-payment',
        'PayPal_Braintree/js/actions/get-shipping-methods',
        'PayPal_Braintree/js/actions/get-totals',
        'PayPal_Braintree/js/actions/set-shipping-information',
        'PayPal_Braintree/js/actions/update-coupon',
        'PayPal_Braintree/js/actions/update-totals',
        'PayPal_Braintree/js/helper/get-apple-pay-line-items',
        'PayPal_Braintree/js/helper/is-cart-virtual',
        'PayPal_Braintree/js/helper/remove-non-digit-characters',
        'PayPal_Braintree/js/model/region-data',
    ],
    function (
        $,
        _,
        Component,
        $t,
        customerData,
        createPayment,
        getShippingMethods,
        getTotals,
        setShippingInformation,
        updateCoupon,
        updateTotals,
        getApplePayLineItems,
        isCartVirtual,
        removeNonDigitCharacters,
        regionDataModel,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                clientToken: null,
                quoteId: 0,
                displayName: null,
                actionSuccess: null,
                grandTotalAmount: 0,
                currencyCode: null,
                storeCode: 'default',
                priceIncludesTax: true,
                shippingAddress: {},
                shippingMethods: {},
                isPdp: false,
                isVirtual: false,
            },

            /**
             * Set & get api token
             */
            setClientToken: function (value) {
                this.clientToken = value;
            },
            getClientToken: function () {
                return this.clientToken;
            },

            /**
             * Set and get quote id
             */
            setQuoteId: function (value) {
                this.quoteId = value;
            },
            getQuoteId: function () {
                return this.quoteId;
            },

            /**
             * Set and get display name
             */
            setDisplayName: function (value) {
                this.displayName = value;
            },
            getDisplayName: function () {
                return this.displayName;
            },

            /**
             * Set and get success redirection url
             */
            setActionSuccess: function (value) {
                this.actionSuccess = value;
            },
            getActionSuccess: function () {
                return this.actionSuccess;
            },

            /**
             * Set and get grand total
             */
            setGrandTotalAmount: function (value) {
                this.grandTotalAmount = parseFloat(value).toFixed(2);
            },
            getGrandTotalAmount: function () {
                return parseFloat(this.grandTotalAmount);
            },

            /**
             * Set and get currency code
             */
            setCurrencyCode: function (value) {
                this.currencyCode = value;
            },
            getCurrencyCode: function () {
                return this.currencyCode;
            },

            /**
             * Set and get store code
             */
            setStoreCode: function (value) {
                this.storeCode = value;
            },
            getStoreCode: function () {
                return this.storeCode;
            },

            /**
             * Set and get store code
             */
            setPriceIncludesTax: function (value) {
                this.priceIncludesTax = value;
            },
            getPriceIncludesTax: function () {
                return this.priceIncludesTax;
            },

            /**
             * Set & get current offers added to cart
             */
            setCurrentOffer: function (value) {
                this.currentOffer = value;
            },
            getCurrentOffer: function () {
                return this.currentOffer;
            },

            /**
             * Set & get is PDP.
             */
            setIsPdp: function (value) {
                this.isPdp = value;
            },
            getIsPdp: function () {
                return this.isPdp;
            },

            /**
             * Set & get is virtual.
             */
            setIsVirtual: function (value) {
                this.isVirtual = value;
            },
            getIsVirtual: function () {
                return this.isVirtual;
            },

            /**
             * Helper to determine whether to shop the shipping information or not.
             */
            getAddShipping: function () {
                if (this.getIsPdp()) {
                    return !isCartVirtual() || !this.getIsVirtual()
                } else {
                    return !isCartVirtual();
                }
            },

            /**
             * Payment request info
             */
            getPaymentRequest: function () {
                const paymentRequest = {
                    currencyCode: this.getCurrencyCode(),
                    total: {
                        label: this.getDisplayName(),
                        amount: this.getGrandTotalAmount()
                    },
                    requiredShippingContactFields: ['email', 'phone', 'name'],
                    requiredBillingContactFields: ['postalAddress', 'name'],
                    supportsCouponCode: true,
                };

                if (this.getAddShipping()) {
                    paymentRequest.requiredShippingContactFields.push('postalAddress');
                }

                return paymentRequest;
            },

            /**
             * Retrieve shipping methods based on address
             */
            onShippingContactSelect: function (event, session) {
                // Get the address.
                let address = event.shippingContact,

                    // Create a payload.
                    payload = {
                        address: {
                            city: address.locality,
                            region: address.administrativeArea,
                            country_id: address.countryCode.toUpperCase(),
                            postcode: address.postalCode,
                            save_in_address_book: 0
                        }
                    };

                this.shippingAddress = payload.address;

                getShippingMethods(payload, this.getStoreCode(), this.getQuoteId())
                .done(function (result) {
                    // Stop if no shipping methods.
                    let virtualFlag = false,
                        shippingMethods = [],
                        totalsPayload = {};

                    if (result.length === 0) {
                        let productItems = customerData.get('cart')().items;

                        _.each(productItems,
                            function (item) {
                                if (item.is_virtual || item.product_type === 'bundle') {
                                    virtualFlag = true;
                                } else {
                                    virtualFlag = false;
                                }
                            }
                        );
                        if (!virtualFlag) {
                            const errors = {
                                errors: [
                                    new window.ApplePayError(
                                        'addressUnserviceable',
                                        'postalAddress',
                                        $t('Unable to get shipping method for selected address.')
                                    )
                                ],
                                newTotal: {
                                    label: this.getDisplayName(),
                                    amount: '0.00',
                                    type: 'pending'
                                }
                            };

                            session.completeShippingContactSelection(errors);
                            return false;
                        }
                    }

                    this.shippingMethods = {};

                    // Format shipping methods array.
                    for (let i = 0; i < result.length; i++) {
                        if (typeof result[i].method_code !== 'string') {
                            continue;
                        }

                        let method = {
                            identifier: result[i].method_code,
                            label: result[i].method_title,
                            detail: result[i].carrier_title ? result[i].carrier_title : '',
                            amount: parseFloat(result[i].amount).toFixed(2)
                        };

                        // Add method object to array.
                        shippingMethods.push(method);

                        this.shippingMethods[result[i].method_code] = result[i];

                        if (!this.shippingMethod) {
                            this.shippingMethod = result[i].method_code;
                        }
                    }

                    // Create payload to get totals
                    totalsPayload = {
                        'addressInformation': {
                            'address': {
                                'countryId': this.shippingAddress.country_id,
                                'region': this.shippingAddress.region,
                                'regionId': regionDataModel.getRegionId(
                                    this.shippingAddress.country_id, this.shippingAddress.region),
                                'postcode': this.shippingAddress.postcode
                            },
                            'shipping_method_code': virtualFlag
                                ? null : this.shippingMethods[shippingMethods[0].identifier].method_code,
                            'shipping_carrier_code': virtualFlag
                                ? null : this.shippingMethods[shippingMethods[0].identifier].carrier_code
                        }
                    };

                    // POST to endpoint to get totals, using 1st shipping method
                    updateTotals(totalsPayload, this.getStoreCode(), this.getQuoteId())
                    .done(function (totals) {
                        // Set total
                        this.setGrandTotalAmount(totals.base_grand_total);

                        // Pass shipping methods back
                        session.completeShippingContactSelection(
                            window.ApplePaySession.STATUS_SUCCESS,
                            shippingMethods,
                            {
                                label: this.getDisplayName(),
                                amount: this.getGrandTotalAmount()
                            },
                            getApplePayLineItems(totals, this.getPriceIncludesTax()),
                        );
                    }.bind(this)).fail(function (error) {
                        const errors = {
                            errors: [
                                new window.ApplePayError(
                                    'unknown',
                                    'postalAddress',
                                    $t('We\'re unable to fetch the cart totals for you. Please try an alternative payment method.')
                                )
                            ],
                            newTotal: {
                                label: this.getDisplayName(),
                                amount: '0.00',
                                type: 'pending'
                            }
                        };

                        session.completeShippingContactSelection(errors);
                        console.error('Braintree ApplePay: Unable to get totals', error);
                        return false;
                    }.bind(this));

                }.bind(this)).fail(function (result) {
                    const errors = {
                        errors: [
                            new window.ApplePayError(
                                'addressUnserviceable',
                                'postalAddress',
                                $t('Unable to get shipping method for selected address.')
                            )
                        ],
                        newTotal: {
                            label: this.getDisplayName(),
                            amount: '0.00',
                            type: 'pending'
                        }
                    };

                    session.completeShippingContactSelection(errors);
                    console.error('Braintree ApplePay: Unable to find shipping methods for estimate-shipping-methods', result);
                    return false;
                }.bind(this));
            },

            /**
             * Record which shipping method has been selected & Updated totals
             */
            onShippingMethodSelect: function (event, session) {
                let shippingMethod = event.shippingMethod,
                    payload = {
                        'addressInformation': {
                            'address': {
                                'countryId': this.shippingAddress.country_id,
                                'region': this.shippingAddress.region,
                                'regionId': regionDataModel.getRegionId(this.shippingAddress.country_id,
                                    this.shippingAddress.region),
                                'postcode': this.shippingAddress.postcode
                            },
                            'shipping_method_code': this.shippingMethods[shippingMethod.identifier].method_code,
                            'shipping_carrier_code': this.shippingMethods[shippingMethod.identifier].carrier_code
                        }
                    };

                this.shippingMethod = shippingMethod.identifier;

                updateTotals(payload, this.getStoreCode(), this.getQuoteId())
                .done(function (r) {
                    this.setGrandTotalAmount(r.base_grand_total);

                    session.completeShippingMethodSelection(
                        window.ApplePaySession.STATUS_SUCCESS,
                        {
                            label: this.getDisplayName(),
                            amount: this.getGrandTotalAmount()
                        },
                        getApplePayLineItems(r, this.getPriceIncludesTax())
                    );
                }.bind(this));
            },

            onCouponCodeChanged: async function (event, session) {
                try {
                    const couponCode = event.couponCode;

                    if (couponCode !== this.getCurrentOffer() && this.getCurrentOffer()) {
                        await updateCoupon(
                            'removeCouponFromCart',
                            this.getCurrentOffer(),
                            1,
                            this.getStoreCode(),
                            this.getQuoteId()
                        );
                    }

                    if (couponCode) {
                        const method = couponCode
                            ? 'applyCouponToCart'
                            : 'removeCouponFromCart';

                        await updateCoupon(
                            method,
                            couponCode,
                            1,
                            this.getStoreCode(),
                            this.getQuoteId()
                        );
                    }

                    this.setCurrentOffer(couponCode);

                    const totals = await getTotals(this.getStoreCode(), this.getQuoteId());

                    this.setGrandTotalAmount(totals.base_grand_total);

                    session.completeCouponCodeChange({
                        newTotal: {
                            label: this.getDisplayName(),
                            amount: this.getGrandTotalAmount(),
                            type: 'final'
                        },
                        newLineItems: getApplePayLineItems(totals, this.getPriceIncludesTax())
                    });
                } catch (error) {
                    const totals = await getTotals(this.getStoreCode(), this.getQuoteId());

                    this.setGrandTotalAmount(totals.base_grand_total);

                    const errors = {
                        errors: [
                            new window.ApplePayError('couponCodeInvalid', 'name', error.message || $t('Unable to process the offer.'))
                        ],
                        newTotal: {
                            label: this.getDisplayName(),
                            amount: this.getGrandTotalAmount(),
                            type: 'final'
                        },
                        newLineItems: getApplePayLineItems(totals, this.getPriceIncludesTax())
                    };
                    session.completeCouponCodeChange(errors);
                }
            },

            /**
             * Place the order
             */
            startPlaceOrder: function (nonce, event, session, device_data) {
                let shippingContact = event.payment.shippingContact,
                    billingContact = event.payment.billingContact,
                    billingAddress = {
                        'email': shippingContact.emailAddress,
                        'telephone': removeNonDigitCharacters(_.get(shippingContact, 'phoneNumber', '')),
                        'firstname': billingContact.givenName,
                        'lastname': billingContact.familyName,
                        'street': billingContact.addressLines,
                        'city': billingContact.locality,
                        'region': billingContact.administrativeArea,
                        'region_id': regionDataModel.getRegionId(
                            billingContact.countryCode.toUpperCase(), billingContact.administrativeArea),
                        'region_code': null,
                        'country_id': billingContact.countryCode.toUpperCase(),
                        'postcode': billingContact.postalCode,
                        'same_as_billing': 0,
                        'customer_address_id': 0,
                        'save_in_address_book': 0
                    },
                    shippingPromise = Promise.resolve();

                // Set addresses if the existing cart isn't virtual or the newly added product isn't virtual.
                if (this.getAddShipping()) {
                    const payload = {
                        'addressInformation': {
                            'shipping_address': {
                                'email': shippingContact.emailAddress,
                                'telephone': removeNonDigitCharacters(_.get(shippingContact, 'phoneNumber', '')),
                                'firstname': shippingContact.givenName,
                                'lastname': shippingContact.familyName,
                                'street': shippingContact.addressLines,
                                'city': shippingContact.locality,
                                'region': shippingContact.administrativeArea,
                                'region_id': regionDataModel.getRegionId(
                                    shippingContact.countryCode.toUpperCase(), shippingContact.administrativeArea),
                                'region_code': null,
                                'country_id': shippingContact.countryCode.toUpperCase(),
                                'postcode': shippingContact.postalCode,
                                'same_as_billing': 0,
                                'customer_address_id': 0,
                                'save_in_address_book': 0
                            },
                            'billing_address': billingAddress,
                            'shipping_method_code': this.shippingMethod
                                ? this.shippingMethods[this.shippingMethod].method_code : '' ,
                            'shipping_carrier_code': this.shippingMethod
                                ? this.shippingMethods[this.shippingMethod].carrier_code : ''
                        }
                    };

                    shippingPromise = setShippingInformation(payload, this.getStoreCode(), this.getQuoteId());
                }

                    shippingPromise.then(() => {
                        // Submit payment information
                        let paymentInformation = {
                            'email': shippingContact.emailAddress,
                            'paymentMethod': {
                                'method': 'braintree_applepay',
                                'additional_data': {
                                    'payment_method_nonce': nonce,
                                    'device_data': device_data
                                }
                            },
                            'billing_address': billingAddress
                        };

                        return createPayment(paymentInformation, this.getStoreCode(), this.getQuoteId())
                    })
                    .then(() => {
                        customerData.invalidate(['cart']);
                    })
                    .then(() => {
                        session.completePayment(window.ApplePaySession.STATUS_SUCCESS);
                        document.location = this.getActionSuccess();
                    })
                    .catch(function () {
                        session.completePayment(window.ApplePaySession.STATUS_FAILURE);
                        alert($t('We\'re unable to take your payment through Apple Pay. Please try an again or use an alternative payment method.'));
                        return false;
                    });
            }
        });
    });

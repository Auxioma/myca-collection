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
 * Braintree Google Pay button api
 **/
define([
    'uiComponent',
    'underscore',
    'jquery',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_Catalog/js/price-utils',
    'PayPal_Braintree/js/actions/create-payment',
    'PayPal_Braintree/js/actions/get-shipping-methods',
    'PayPal_Braintree/js/actions/get-coupons',
    'PayPal_Braintree/js/actions/get-totals',
    'PayPal_Braintree/js/actions/set-shipping-information',
    'PayPal_Braintree/js/actions/update-coupon',
    'PayPal_Braintree/js/actions/update-totals',
    'PayPal_Braintree/js/form-builder',
    'PayPal_Braintree/js/googlepay/implementations/shortcut/3d-secure',
    'PayPal_Braintree/js/googlepay/model/parsed-response',
    'PayPal_Braintree/js/googlepay/model/payment-data',
    'PayPal_Braintree/js/helper/addresses/map-googlepay-payment-information',
    'PayPal_Braintree/js/helper/addresses/map-googlepay-shipping-information',
    'PayPal_Braintree/js/helper/get-google-pay-line-items',
    'PayPal_Braintree/js/helper/is-cart-virtual',
    'PayPal_Braintree/js/helper/remove-non-digit-characters',
    'PayPal_Braintree/js/helper/submit-review-page',
    'PayPal_Braintree/js/model/region-data',
    'PayPal_Braintree/js/view/payment/adapter',
    'PayPal_Braintree/js/view/payment/validator-manager'
], function (
    Component,
    _,
    $,
    $t,
    customerData,
    priceUtils,
    createPayment,
    getShippingMethods,
    getCoupons,
    getTotals,
    setShippingInformation,
    updateCoupon,
    updateTotals,
    formBuilder,
    threeDSecureValidator,
    parsedResponseModel,
    paymentDataModel,
    mapGooglePayPaymentInformation,
    mapGooglePayShippingInformation,
    getGooglePayLineItems,
    isCartVirtual,
    removeNonDigitCharacters,
    submitReviewPage,
    regionDataModel,
    braintreeMainAdapter,
    validatorManager
) {
    'use strict';

    return Component.extend({
        defaults: {
            validatorManager: validatorManager,
            threeDSecureValidator: threeDSecureValidator,
            clientToken: null,
            merchantId: null,
            currencyCode: null,
            actionSuccess: null,
            amount: null,
            cardTypes: [],
            shippingMethods: {},
            shippingMethodCode: null,
            btnColor: 0,
            email: null,
            paymentMethodNonce: null,
            creditCardBin: null,
            element: null,
            priceFormat: [],
            multiCouponLimit: 0,
            currentOffers: []
        },

        /**
         * Set & get environment
         * "PRODUCTION" or "TEST"
         */
        setEnvironment: function (value) {
            this.environment = value;
        },
        getEnvironment: function () {
            return this.environment;
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
         * Set and get display name
         */
        setMerchantId: function (value) {
            this.merchantId = value;
        },
        getMerchantId: function () {
            return this.merchantId;
        },

        /**
         * Set and get currency code
         */
        setAmount: function (value) {
            this.amount = parseFloat(value).toFixed(2);
        },
        getAmount: function () {
            return this.amount;
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
         * Set and get success redirection url
         */
        setActionSuccess: function (value) {
            this.actionSuccess = value;
        },
        getActionSuccess: function () {
            return this.actionSuccess;
        },

        /**
         * Set and get success redirection url
         */
        setCardTypes: function (value) {
            this.cardTypes = value;
        },
        getCardTypes: function () {
            return this.cardTypes;
        },

        /**
         * BTN Color
         */
        setBtnColor: function (value) {
            this.btnColor = value;
        },
        getBtnColor: function () {
            return this.btnColor;
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
         * Set and get store code
         */
        setStoreCode: function (value) {
            this.storeCode = value;
        },
        getStoreCode: function () {
            return this.storeCode;
        },

        /**
         * Set and get success redirection url
         */
        setSkipReview: function (value) {
            this.skipReview = value;
        },
        getSkipReview: function () {
            return this.skipReview;
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
         * Set and get the current element
         */
        setElement: function (value) {
            this.element = value;
        },
        getElement: function () {
            return this.element;
        },

        /**
         * Set and get the current element
         */
        setPriceFormat: function (value) {
            this.priceFormat = value;
        },
        getPriceFormat: function () {
            return this.priceFormat;
        },

        /**
         * Set & get accepts offers within payment sheet
         */
        setMultiCouponLimit: function (value) {
            this.multiCouponLimit = parseInt(value, 10);
        },
        getMultiCouponLimit: function () {
            return this.multiCouponLimit;
        },

        /**
         * Set & get current offers added to cart
         */
        setCurrentOffers: function (value) {
            this.currentOffers = value;
        },
        getCurrentOffers: function () {
            return this.currentOffers;
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
                return !isCartVirtual() || !this.getIsVirtual();
            }

            return !isCartVirtual();
        },

        /**
         * Add the 3D Secure validator config.
         *
         * @param {object} value
         */
        setThreeDSecureValidatorConfig: function (value) {
            this.threeDSecureValidator.setConfig(value);
        },

        /**
         * Add the 3D Secure validator to the validation manager with amount & billing address data set.
         * It will be added only if 3D Secure is active.
         */
        addThreeDSecureValidator: function () {
            this.threeDSecureValidator.setBillingAddress(this.getThreeDSecureBillingAddressData());
            this.threeDSecureValidator.setShippingAddress(this.getThreeDSecureShippingAddressData());
            this.threeDSecureValidator.setTotalAmount(this.getAmount());

            this.validatorManager.add(this.threeDSecureValidator);
        },

        /**
         * Payment request info
         */
        getPaymentRequest: async function () {
            const totals = await getTotals(this.getStoreCode(), this.getQuoteId()),
                coupons = await getCoupons(this.getStoreCode(), this.getQuoteId()),
                displayShippingOptions = this.getAddShipping() && this.getSkipReview(),
                callbackIntents = ['PAYMENT_AUTHORIZATION'];

            this.setCurrentOffers(coupons);

            let offers = this.getOffers(totals),
                result = {};

            if (this.getAddShipping()) {
                callbackIntents.push('SHIPPING_ADDRESS');
            }

            if (displayShippingOptions) {
                callbackIntents.push('SHIPPING_OPTION');
            }

            if (this.getMultiCouponLimit()) {
                callbackIntents.push('OFFER');
            }

            result = {
                transactionInfo: {
                    totalPriceStatus: 'ESTIMATED',
                    totalPrice: this.getAmount(),
                    currencyCode: this.getCurrencyCode(),
                    displayItems: getGooglePayLineItems(totals, this.getPriceIncludesTax()),
                    totalPriceLabel: $t('Total')
                },
                offerInfo: {
                    offers
                },
                allowedPaymentMethods: [
                    {
                        'type': 'CARD',
                        'parameters': {
                            'allowedCardNetworks': this.getCardTypes(),
                            'billingAddressRequired': true,
                            'billingAddressParameters': {
                                format: 'FULL',
                                phoneNumberRequired: true
                            }
                        }
                    }
                ],
                shippingAddressRequired: this.getAddShipping(),
                shippingOptionRequired: displayShippingOptions,
                shippingAddressParameters: {
                    phoneNumberRequired: true
                },
                emailRequired: true,
                callbackIntents
            };

            if (this.getEnvironment() !== 'TEST') {
                result.merchantInfo = {merchantId: this.getMerchantId()};
            }

            return result;
        },

        /**
         * Update order on payment data changed
         *
         * @param data
         * @returns {Promise<unknown>}
         */
        onPaymentDataChanged: function (data) {
            return new Promise((resolve) => {
                const {callbackTrigger} = data,
                    shippingCallbacks = ['INITIALIZE', 'SHIPPING_ADDRESS', 'SHIPPING_OPTION'];

                if (!this.getAddShipping() && callbackTrigger === 'INITIALIZE') {
                    getTotals(this.getStoreCode(), this.getQuoteId())
                        .then((totals) => {
                            resolve(this.formatPaymentData(totals));
                        });
                } else if (shippingCallbacks.includes(callbackTrigger)) {
                    this.setShippingDetails(data, resolve);
                } else {
                    this.setOfferDetails(data, resolve);
                }
            });
        },

        /**
         * Set shipping details
         *
         * @param data
         * @param resolve
         */
        setShippingDetails: function (data, resolve) {
            const payload = {
                address: {
                    city: data.shippingAddress.locality,
                    region: data.shippingAddress.administrativeArea,
                    country_id: data.shippingAddress.countryCode,
                    postcode: data.shippingAddress.postalCode,
                    save_in_address_book: 0
                }
            };

            let shippingMethods = Promise.resolve();

            if (this.getAddShipping()) {
                shippingMethods = getShippingMethods(
                    payload,
                    this.getStoreCode(), this.getQuoteId()
                ).then((response) => {
                    const methods = response.filter(({available}) => available);

                    // Any error message means we need to exit by resolving with an error state.
                    if (!methods.length) {
                        resolve({
                            ...this.paymentData,
                            error: {
                                reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                message: $t('There are no shipping methods available for the selected address.'),
                                intent: 'SHIPPING_ADDRESS'
                            }
                        });
                        return;
                    }

                    return methods;
                });
            }

            shippingMethods.then((methods) => {
                let selectedShipping, totalsPayload;

                if (this.getAddShipping() && this.getSkipReview()) {
                    selectedShipping = data.shippingOptionData.id === 'shipping_option_unselected'
                        ? methods[0]
                        : methods.find(({method_code: id}) => id === data.shippingOptionData.id) || methods[0];

                    this.shippingMethodCode = selectedShipping.method_code;
                }

                // Create payload to get totals
                totalsPayload = {
                    'addressInformation': {
                        'address': {
                            'countryId': data.shippingAddress.countryCode,
                            'region': data.shippingAddress.administrativeArea,
                            'regionId': regionDataModel.getRegionId(
                                data.shippingAddress.countryCode,
                                data.shippingAddress.administrativeArea
                            ),
                            'postcode': data.shippingAddress.postalCode
                        },
                        'shipping_method_code': selectedShipping?.method_code,
                        'shipping_carrier_code': selectedShipping?.carrier_code
                    }
                };

                updateTotals(totalsPayload, this.getStoreCode(), this.getQuoteId())
                    .then((totals) => {
                        const paymentDataRequestUpdate = this.formatPaymentData(totals, methods, selectedShipping);

                        resolve(paymentDataRequestUpdate);
                    });
            });
        },

        /**
         * Set offer details
         *
         * @param data
         * @param resolve
         * @returns {Promise<void>}
         */
        setOfferDetails: async function (data, resolve) {
            try {
                const redemptionCodes = data.offerData.redemptionCodes,
                    currentOffers = this.getCurrentOffers(),

                    appliedCoupons = currentOffers?.prices?.discounts
                        ? currentOffers?.applied_coupons?.map(({code}) => code) || []
                        : [],

                    method = appliedCoupons.length < redemptionCodes.length
                        ? 'applyCouponToCart'
                        : 'removeCouponFromCart',

                    offer = appliedCoupons.filter(x => !redemptionCodes.includes(x))
                        .concat(redemptionCodes.filter(x => !appliedCoupons.includes(x))),

                    coupons = await updateCoupon(
                        method,
                        offer?.[0],
                        this.getMultiCouponLimit(),
                        this.getStoreCode(),
                        this.getQuoteId()
                    ),
                    totals = await getTotals(this.getStoreCode(), this.getQuoteId()),
                    paymentDataRequestUpdate = this.formatPaymentData(totals);

                if (method === 'applyCouponToCart' && !coupons.prices.discounts) {
                    throw new Error();
                }

                this.setCurrentOffers(coupons);

                resolve(paymentDataRequestUpdate);
            } catch (error) {
                resolve({
                    ...this.paymentData,
                    error: {
                        reason: 'OFFER_INVALID',
                        message: error.message || $t('Unable to process the offer.'),
                        intent: 'OFFER'
                    }
                });
            }
        },

        /**
         * Place the order
         *
         * @param paymentData
         * @returns {Promise<unknown>}
         */
        startPlaceOrder: function (paymentData) {
            // Persist the paymentData (shipping address etc.)
            return new Promise((resolve) => {
                paymentDataModel.setPaymentMethodData(_.get(
                    paymentData,
                    'paymentMethodData',
                    null
                ));
                paymentDataModel.setEmail(_.get(paymentData, 'email', ''));
                paymentDataModel.setShippingAddress(_.get(
                    paymentData,
                    'shippingAddress',
                    null
                ));

                const googlePaymentInstance = braintreeMainAdapter.getGooglePayInstance();

                googlePaymentInstance.parseResponse(paymentData).then(function (result) {
                    parsedResponseModel.setNonce(result.nonce);
                    parsedResponseModel.setIsNetworkTokenized(_.get(
                        result,
                        ['details', 'isNetworkTokenized'],
                        false
                    ));
                    parsedResponseModel.setBin(_.get(
                        result,
                        ['details', 'bin'],
                        null
                    ));

                    let payload = {
                        details: {
                            shippingAddress: this.getShippingAddressData(),
                            billingAddress: this.getBillingAddressData()
                        },
                        nonce: this.paymentMethodNonce,
                        isNetworkTokenized: parsedResponseModel.getIsNetworkTokenized(),
                        deviceData: braintreeMainAdapter.deviceData
                    };

                    payload.details.name = payload.details.shippingAddress?.name
                        || payload.details.billingAddress?.name;

                    this.email = paymentDataModel.getEmail();
                    this.paymentMethodNonce = parsedResponseModel.getNonce();
                    this.creditCardBin = parsedResponseModel.getBin();

                    if (parsedResponseModel.getIsNetworkTokenized() === false) {
                        /* Add 3D Secure verification to payment & validate payment for non network tokenized cards */
                        this.addThreeDSecureValidator();

                        this.validatorManager.validate(this, function () {
                            /* Set the new nonce from the 3DS verification */
                            payload.nonce = this.paymentMethodNonce;

                            if (!this.getSkipReview()) {
                                return submitReviewPage(payload, this.getElement(), 'googlepay');
                            }

                            let shippingPromise = Promise.resolve(),
                                shippingInformation = {};

                            if (this.getAddShipping()) {
                                const shippingMethod = this.shippingMethods[this.shippingMethodCode];

                                shippingInformation = mapGooglePayShippingInformation(payload, shippingMethod);
                                shippingPromise = setShippingInformation(
                                    shippingInformation,
                                    this.getStoreCode(),
                                    this.getQuoteId()
                                );
                            }

                            const paymentInformation = mapGooglePayPaymentInformation(
                                payload,
                                shippingInformation?.addressInformation?.shipping_address || {}
                            );

                            return shippingPromise
                                .then(() => createPayment(paymentInformation, this.getStoreCode(), this.getQuoteId()))
                                .then(() => {
                                    customerData.invalidate(['cart']);
                                })
                                .then(() => {
                                    document.location = this.getActionSuccess();
                                })
                                .catch(function (error) {
                                    $('body').trigger('processStop');
                                    console.error(error);
                                });
                        }.bind(this), function () {
                            this.paymentMethodNonce = null;
                            this.creditCardBin = null;
                        }.bind(this));

                        resolve({
                            transactionState: 'SUCCESS'
                        });
                    } else {
                        formBuilder.build({
                            action: this.getActionSuccess(),
                            fields: {
                                result: JSON.stringify(payload)
                            }
                        }).submit();
                    }
                }.bind(this));
            });
        },

        /**
         * Get the shipping address from the payment data model which should already be set by the calling script.
         *
         * @return {?Object}
         */
        getShippingAddressData: function () {
            const shippingAddress = paymentDataModel.getShippingAddress();

            if (shippingAddress === null) {
                return null;
            }

            return {
                streetAddress: shippingAddress.address1 + '\n' + shippingAddress.address2,
                locality: shippingAddress.locality,
                postalCode: shippingAddress.postalCode,
                countryCodeAlpha2: shippingAddress.countryCode,
                email: paymentDataModel.getEmail(),
                name: shippingAddress.name,
                telephone: removeNonDigitCharacters(_.get(shippingAddress, 'phoneNumber', '')),
                region: _.get(shippingAddress, 'administrativeArea', '')
            };
        },

        /**
         * Get the billing address from the payment data model which should already be set by the calling script.
         *
         * @return {?Object}
         */
        getBillingAddressData: function () {
            const paymentMethodData = paymentDataModel.getPaymentMethodData(),
                billingAddress = _.get(paymentMethodData, ['info', 'billingAddress'], null);

            if (paymentMethodData === null) {
                return null;
            }

            if (billingAddress === null) {
                return null;
            }

            return {
                streetAddress: billingAddress.address1 + '\n' + billingAddress.address2,
                locality: billingAddress.locality,
                postalCode: billingAddress.postalCode,
                countryCodeAlpha2: billingAddress.countryCode,
                email: paymentDataModel.getEmail(),
                name: billingAddress.name,
                telephone: removeNonDigitCharacters(_.get(billingAddress, 'phoneNumber', '')),
                region: _.get(billingAddress, 'administrativeArea', '')
            };
        },

        /**
         * Get the billing address data as required for 3D Secure verification.
         *
         * For First & last name, use a simple split by space.
         *
         * @return {?Object}
         */
        getThreeDSecureBillingAddressData: function () {
            let paymentMethodData = paymentDataModel.getPaymentMethodData(),
                billingAddress = _.get(paymentMethodData, ['info', 'billingAddress'], null);

            if (paymentMethodData === null) {
                return null;
            }

            if (billingAddress === null) {
                return null;
            }

            return {
                firstname: billingAddress.name.substring(0, billingAddress.name.indexOf(' ')),
                lastname: billingAddress.name.substring(billingAddress.name.indexOf(' ') + 1),
                telephone: removeNonDigitCharacters(_.get(billingAddress, 'phoneNumber', '')),
                street: [
                    billingAddress.address1,
                    billingAddress.address2
                ],
                city: billingAddress.locality,
                regionCode: _.get(billingAddress, 'administrativeArea', ''),
                postcode: billingAddress.postalCode,
                countryId: billingAddress.countryCode
            };
        },

        /**
         * Get the shipping address data as required for 3D Secure verification.
         *
         * For First & last name, use a simple split by space.
         *
         * @return {?Object}
         */
        getThreeDSecureShippingAddressData: function () {
            let shippingAddress = paymentDataModel.getShippingAddress();

            if (shippingAddress === null) {
                return null;
            }

            return {
                firstname: shippingAddress.name.substring(0, shippingAddress.name.indexOf(' ')),
                lastname: shippingAddress.name.substring(shippingAddress.name.indexOf(' ') + 1),
                telephone: removeNonDigitCharacters(_.get(shippingAddress, 'phoneNumber', '')),
                street: [
                    shippingAddress.address1,
                    shippingAddress.address2
                ],
                city: shippingAddress.locality,
                regionCode: _.get(shippingAddress, 'administrativeArea', ''),
                postcode: shippingAddress.postalCode,
                countryId: shippingAddress.countryCode
            };
        },

        /**
         * Format payment data
         *
         * @param totals
         * @param methods
         * @param selectedShipping
         * @returns {{
         *    newOfferInfo: { offers: * },
         *    newTransactionInfo: {
         *      currencyCode: *,
         *      displayItems: *,
         *      totalPrice: *,
         *      totalPriceLabel: *,
         *      totalPriceStatus: string
         *    }
         * }}
         */
        formatPaymentData: function (totals, methods, selectedShipping) {
            let shippingMethods;

            this.setAmount(totals.base_grand_total);

            const paymentDataRequestUpdate = {
                ...this.paymentData,
                newOfferInfo: {offers: this.getOffers(totals)},
                newTransactionInfo: {
                    currencyCode: totals.base_currency_code,
                    displayItems: getGooglePayLineItems(totals, this.getPriceIncludesTax()),
                    totalPrice: this.getAmount(),
                    totalPriceLabel: $t('Total'),
                    totalPriceStatus: 'FINAL'
                }
            };

            if (methods) {
                shippingMethods = methods.map((shippingMethod) => {
                    const price = priceUtils.formatPriceLocale(shippingMethod.price_incl_tax, this.getPriceFormat()),
                        label = shippingMethod.method_title
                            ? `${price}: ${shippingMethod.method_title}`
                            : price,
                        description = shippingMethod.carrier_title
                            ? shippingMethod.carrier_title
                            : '';

                    this.shippingMethods[shippingMethod.method_code] = shippingMethod;

                    return {
                        id: shippingMethod.method_code,
                        label: label,
                        description
                    };
                });
            }

            if (shippingMethods && selectedShipping) {
                paymentDataRequestUpdate.newShippingOptionParameters = {
                    defaultSelectedOptionId: selectedShipping.method_code,
                    shippingOptions: shippingMethods
                };
            }

            this.paymentData = paymentDataRequestUpdate;

            return paymentDataRequestUpdate;
        },

        /**
         * Get offers
         *
         * @param totals
         * @returns {{redemptionCode: *, description: *}[]|*[]}
         */
        getOffers: function (totals) {
            const codes = totals.extension_attributes?.coupon_codes || [];

            return codes.map((code) => {
                return {
                    'redemptionCode': code,
                    'description': priceUtils.formatPriceLocale(this.getOfferAmount(code), this.getPriceFormat())
                };
            }) || [];
        },

        /**
         * Get offer amount
         *
         * @param code
         * @returns {*|number}
         */
        getOfferAmount: function (code) {
            const coupons = this.getCurrentOffers(),
                price = coupons?.prices?.discounts?.find((discount) => {
                    return code === discount.coupon.code;
                });

            return price?.amount?.value || 0;
        }
    });
});

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
define(
    [
        'uiComponent',
        'underscore',
        'jquery',
        'Magento_Customer/js/customer-data',
        'mage/translate',
        'mage/storage',
        'braintree',
        'braintreeCheckoutPayPalAdapter',
        'braintreeDataCollector',
        'braintreePayPalCheckout',
        'PayPal_Braintree/js/actions/create-payment',
        'PayPal_Braintree/js/actions/get-shipping-methods',
        'PayPal_Braintree/js/actions/set-shipping-information',
        'PayPal_Braintree/js/actions/update-totals',
        'PayPal_Braintree/js/helper/check-guest-checkout',
        'PayPal_Braintree/js/helper/is-cart-virtual',
        'PayPal_Braintree/js/helper/addresses/map-paypal-payment-information',
        'PayPal_Braintree/js/helper/addresses/map-paypal-shipping-information',
        'PayPal_Braintree/js/helper/get-api-url',
        'PayPal_Braintree/js/helper/submit-review-page',
        'PayPal_Braintree/js/helper/remove-non-digit-characters',
        'PayPal_Braintree/js/helper/replace-single-quote-character',
        'PayPal_Braintree/js/model/region-data',
        'mage/url',
        'domReady!'
    ],
    function (
        Component,
        _,
        $,
        customerData,
        $t,
        storage,
        braintree,
        Braintree,
        dataCollector,
        paypalCheckout,
        createPayment,
        getShippingMethods,
        setShippingInformation,
        updateTotals,
        checkGuestCheckout,
        isCartVirtual,
        mapPayPalPaymentInformation,
        mapPayPalShippingInformation,
        getApiUrl,
        submitReviewPage,
        removeNonDigitCharacters,
        replaceSingleQuoteCharacter,
        regionDataModel,
        url
    ) {
        'use strict';

        return Component.extend({
            events: {
                onClick: null,
                onCancel: null,
                onError: null
            },
            currencyCode: null,
            amount: 0,
            quoteId: 0,
            storeCode: 'default',
            shippingAddress: {},
            shippingMethods: {},
            shippingMethodCode: null,
            buttonIds: [],
            skipReview: null,
            buttonConfig: {},
            pageType: null,
            code: 'braintree_paypal',

            /**
             * Initialize button
             *
             * @param config
             */
            initialize: function (config) {
                this._super(config);

                $(document).on('priceUpdated', (event, displayPrices) => {
                    $('.action-braintree-paypal-message[data-pp-type="product"]')
                        .attr('data-pp-amount', displayPrices.finalPrice.amount);
                });

                this.buttonConfig = config.buttonConfig;
                this.buttonIds = config.buttonIds;
                this.loadSDK(this.buttonConfig);

                window.addEventListener('hashchange', function () {
                    const step = window.location.hash.replace('#', '');

                    if (step === 'shipping') {
                        Braintree.getPayPalInstance()?.teardown(function () {
                            this.loadSDK(this.buttonConfig);
                        }.bind(this));
                    }

                }.bind(this));

                window.addEventListener('paypal:reinit-express', function () {
                    this.loadSDK(this.buttonConfig);
                }.bind(this));

                const cart = customerData.get('cart');

                cart.subscribe(({ braintree_masked_id }) => {
                    this.setQuoteId(braintree_masked_id);
                });

                if (cart()?.braintree_masked_id) {
                    this.setQuoteId(cart().braintree_masked_id);
                }
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
             * Get payment method code
             *
             * @returns {string}
             */
            getCode: function () {
                return this.code;
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
            setSkipReview: function (value) {
                this.skipReview = value;
            },
            getSkipReview: function () {
                return this.skipReview;
            },

            /**
             * Set and get amount
             */
            setAmount: function (value) {
                this.amount = parseFloat(value).toFixed(2);
            },
            getAmount: function () {
                return parseFloat(this.amount).toFixed(2);
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
            setCurrencyCode: function (value) {
                this.currencyCode = value;
            },
            getCurrencyCode: function () {
                return this.currencyCode;
            },

            /**
             * Load Braintree PayPal SDK
             *
             * @param buttonConfig
             */
            loadSDK: function (buttonConfig) {
                // Load SDK
                braintree.create({
                    authorization: buttonConfig.clientToken
                }, function (clientErr, clientInstance) {
                    if (clientErr) {
                        console.error('paypalCheckout error', clientErr);
                        let error = 'PayPal Checkout could not be initialized. Please contact the store owner.';

                        return Braintree.showError(error);
                    }
                    dataCollector.create({
                        client: clientInstance,
                        paypal: true
                    }, function (err) {
                        if (err) {
                            return console.log(err);
                        }
                    });
                    paypalCheckout.create({
                        client: clientInstance
                    }, function (err, paypalCheckoutInstance) {
                        if (typeof paypal !== 'undefined') {
                            this.renderPayPalButtons(paypalCheckoutInstance);
                        } else {
                            let configSDK = {
                                    components: 'buttons,funding-eligibility',
                                    'enable-funding': this.isCreditActive(buttonConfig) ? 'credit' : 'paylater',
                                    currency: buttonConfig.currency,
                                    commit: buttonConfig.skipOrderReviewStep
                                        && (!isCartVirtual() || !buttonConfig.isProductVirtual),
                                    dataAttributes: {},
                                    locale: buttonConfig.locale
                                },
                                buyerCountry = this.getMerchantCountry(buttonConfig);

                            if (buttonConfig.environment === 'sandbox'
                                && (buyerCountry !== '' || buyerCountry !== 'undefined')) {
                                configSDK['buyer-country'] = buyerCountry;
                            }

                            if (buttonConfig.pageType) {
                                configSDK.dataAttributes['page-type'] = buttonConfig.pageType;
                            }

                            if (buttonConfig.cspNonce) {
                                configSDK.dataAttributes['csp-nonce'] = buttonConfig.cspNonce;
                            }

                            paypalCheckoutInstance.loadPayPalSDK(configSDK, function () {
                                this.renderPayPalButtons(paypalCheckoutInstance);
                            }.bind(this));
                        }
                    }.bind(this));
                }.bind(this));
            },

            /**
             * Is Credit enabled
             *
             * @param buttonConfig
             * @returns {boolean}
             */
            isCreditActive: function (buttonConfig) {
                return buttonConfig.isCreditActive;
            },

            /**
             * Get merchant country
             *
             * @param buttonConfig
             * @returns {string}
             */
            getMerchantCountry: function (buttonConfig) {
                return buttonConfig.merchantCountry;
            },

            /**
             * Can cart line items be sent to PayPal?
             *
             * @param buttonConfig
             * @returns {boolean}
             */
            canSendCartLineItems: function (buttonConfig) {
                return buttonConfig.canSendCartLineItems;
            },

            /**
             * Get contact preference
             *
             * @param buttonConfig
             * @returns {boolean}
             */
            getContactPreference: function (buttonConfig) {
                return buttonConfig.contactPreference;
            },

            /**
             * Render PayPal buttons
             *
             * @param paypalCheckoutInstance
             */
            renderPayPalButtons: function (paypalCheckoutInstance) {
                this.payPalButton(paypalCheckoutInstance);
            },

            /**
             * @param paypalCheckoutInstance
             */
            payPalButton: function (paypalCheckoutInstance) {
                let self = this;

                $(this.buttonIds.join(',')).each(function (index, element) {
                    $(element).html('');

                    let currentElement = $(element),
                        style = {
                            label: currentElement.data('label'),
                            color: currentElement.data('color'),
                            shape: currentElement.data('shape')
                        };

                    if (currentElement.data('fundingicons')) {
                        style.fundingicons = currentElement.data('fundingicons');
                    }

                    // set values
                    self.setCurrencyCode(currentElement.data('currency'));
                    self.setAmount(currentElement.data('amount'));
                    self.setStoreCode(currentElement.data('storecode'));
                    self.setActionSuccess(currentElement.data('actionsuccess'));

                    self.setSkipReview(currentElement.data('skiporderreviewstep'));

                    // Render
                    const fundingSource = currentElement.data('funding'),
                        config = {
                            fundingSource,
                            style: style,
                            message: Braintree.getMessage(
                                fundingSource,
                                self.getAmount(),
                                self.buttonConfig.pageType
                            ),

                            createOrder: () => self.createOrder(paypalCheckoutInstance, currentElement),

                            validate: function (actions) {
                                let cart = customerData.get('cart'),
                                    customer = customerData.get('customer'),
                                    declinePayment = false,
                                    isGuestCheckoutAllowed;

                                isGuestCheckoutAllowed = cart().isGuestCheckoutAllowed;
                                declinePayment = !customer().firstname && !isGuestCheckoutAllowed
                                    && typeof isGuestCheckoutAllowed !== 'undefined';

                                if (declinePayment) {
                                    actions.disable();
                                }
                            },

                            onCancel: function () {
                                $('#maincontent').trigger('processStop');
                            },

                            onError: function (errorData) {
                                console.error('paypalCheckout button render error', errorData);
                                $('#maincontent').trigger('processStop');
                            },

                            onClick: self.onClick.bind(self),

                            onApprove: function (approveData) {
                                return paypalCheckoutInstance.tokenizePayment(approveData, function (err, payload) {
                                    if (!self.getSkipReview() || isCartVirtual()) {
                                        payload.details.shippingAddress = self.getShippingAddressData(payload);
                                        payload.details.billingAddress = self.getBillingAddressData(
                                            payload,
                                            currentElement
                                        );

                                        return submitReviewPage(payload, currentElement, 'paypal');
                                    }

                                    let shippingOptionId = payload.shippingOptionId.split('_');

                                    const shippingMethod = {
                                            carrier_code: shippingOptionId[0],
                                            method_code: shippingOptionId[1]
                                        },
                                        shippingInformation = mapPayPalShippingInformation(
                                            payload,
                                            shippingMethod,
                                            self.getContactPreference(self.buttonConfig)
                                        ),
                                        paymentInformation = mapPayPalPaymentInformation(
                                            payload,
                                            currentElement.data('requiredbillingaddress'),
                                            self.getContactPreference(self.buttonConfig)
                                        );

                                    return setShippingInformation(
                                        shippingInformation,
                                        self.getStoreCode(),
                                        self.getQuoteId()
                                    ).then(() => createPayment(
                                        paymentInformation,
                                        self.getStoreCode(),
                                        self.getQuoteId()
                                    )).then(() => {
                                        customerData.invalidate(['cart']);
                                    }).then(() => {
                                        document.location = self.getActionSuccess();
                                    }).catch(function (error) {
                                        self.onError(error);
                                    });
                                });
                            }
                        },

                        button = window.paypal.Buttons(config);

                    if (!button.isEligible()) {
                        console.log('PayPal button is not eligible');
                        currentElement.parent().remove();
                        return;
                    }
                    if (button.isEligible() && $('#' + currentElement.attr('id')).length) {
                        button.render('#' + currentElement.attr('id'));
                    }
                });
            },

            /**
             * PayPal's Create Order event
             *
             * @param paypalCheckoutInstance
             * @param currentElement
             * @returns {Promise<*>}
             */
            createOrder: async function (paypalCheckoutInstance, currentElement) {
                const {cartId, lineItems, amountBreakdown, amount} = await this.getLineItems();

                let paymentOptions = {
                    amount,
                    locale: currentElement.data('locale'),
                    currency: currentElement.data('currency'),
                    flow: 'checkout',
                    enableShippingAddress: true,
                    displayName: currentElement.data('displayname')
                };

                // if 'Skip Order Review Page' config is set to YES then only pass SSSC parameters
                if (this.getSkipReview()) {
                    paymentOptions.shippingCallbackUrl = url.build('braintree/shipping/callback?cart_id=' + cartId);
                    paymentOptions.callbackEvents = ['SHIPPING_ADDRESS', 'SHIPPING_OPTIONS'];
                }

                // If 'Send Cart Line Items for PayPal' config is set YES then only pass line items parameters
                if (this.canSendCartLineItems(this.buttonConfig)) {
                    paymentOptions.lineItems = lineItems;
                    paymentOptions.amountBreakdown = amountBreakdown;
                }

                paymentOptions.contactPreference = 'NO_CONTACT_INFO';
                if (this.getContactPreference(this.buttonConfig)) {
                    paymentOptions.contactPreference = 'UPDATE_CONTACT_INFO';
                }

                return paypalCheckoutInstance.createPayment(paymentOptions);
            },

            /**
             * PayPal's onClick event
             *
             * @returns {boolean}
             */
            onClick: function () {
                if (!checkGuestCheckout()) {
                    return false;
                }

                return true;
            },

            /**
             * Get the shipping address from the payment data model which should already be set by the calling script.
             *
             * @return {?Object}
             */
            getShippingAddressData: function (payload) {
                let accountFirstName = replaceSingleQuoteCharacter(payload.details.firstName),
                    accountLastName = replaceSingleQuoteCharacter(payload.details.lastName),
                    accountEmail = replaceSingleQuoteCharacter(payload.details.email),
                    recipientFirstName = accountFirstName,
                    recipientLastName = accountLastName,
                    address = payload.details.shippingAddress,
                    phone = _.get(payload, ['details', 'phone'], '0000000000');

                // PayPal - Contact module preference
                if (this.getContactPreference(this.buttonConfig)) {
                    phone = address.phone !== 'undefined' ? address.phone : phone;
                    accountEmail = address.recipientEmail !== 'undefined'
                        ? replaceSingleQuoteCharacter(address.recipientEmail)
                        : accountEmail;
                }

                // Map the shipping address correctly
                if (!_.isUndefined(address.recipientName) && _.isString(address.recipientName)) {
                    /**
                     * Trim leading/ending spaces before splitting,
                     * filter to remove array keys with empty values
                     * & set to variable.
                     */
                    const [
                        splitRecipientFirstName,
                        ...splitRecipientLastName
                    ] = address.recipientName.trim().split(' ').filter(n => n);

                    /**
                     * If the split name is not null, and it is an array with
                     * first/last name, use it. Otherwise, keep the default billing first/last name.
                     * This is to avoid cases of old accounts where spaces were allowed to first or
                     * last name in PayPal and the result was an array with empty fields
                     * resulting in empty names in the system.
                     */
                    if (splitRecipientLastName.length) {
                        recipientFirstName = replaceSingleQuoteCharacter(splitRecipientFirstName);
                        recipientLastName = splitRecipientLastName.map(replaceSingleQuoteCharacter).join(' ');
                    }
                }

                return {
                    streetAddress: typeof address.line2 !== 'undefined' && _.isString(address.line2)
                        ? replaceSingleQuoteCharacter(address.line1)
                        + ' ' + replaceSingleQuoteCharacter(address.line2)
                        : replaceSingleQuoteCharacter(address.line1),
                    locality: replaceSingleQuoteCharacter(address.city),
                    postalCode: address.postalCode,
                    countryCodeAlpha2: address.countryCode,
                    email: accountEmail,
                    recipientFirstName: recipientFirstName,
                    recipientLastName: recipientLastName,
                    telephone: removeNonDigitCharacters(phone),
                    region: typeof address.state !== 'undefined'
                        ? replaceSingleQuoteCharacter(address.state)
                        : ''
                };
            },

            /**
             * Get the billing address from the payment data model which should already be set by the calling script.
             *
             * @return {?Object}
             */
            getBillingAddressData: function (payload, currentElement) {
                // Map the billing address correctly
                const isRequiredBillingAddress = currentElement.data('requiredbillingaddress');

                if (isRequiredBillingAddress && typeof payload.details.billingAddress !== 'undefined') {
                    if (!payload.details?.billingAddress?.streetAddress) {
                        return payload.details.shippingAddress;
                    }

                    let billingAddress = payload.details.billingAddress,
                        phone = _.get(payload, ['details', 'phone'], '0000000000'),
                        shippingAddress = payload.details.shippingAddress,
                        accountEmail = replaceSingleQuoteCharacter(payload.details.email);

                    // PayPal - Contact module preference
                    if (this.getContactPreference(this.buttonConfig)) {
                        phone = shippingAddress.phone !== 'undefined' ? shippingAddress.phone : phone;
                        accountEmail = shippingAddress.recipientEmail !== 'undefined'
                            ? replaceSingleQuoteCharacter(shippingAddress.recipientEmail)
                            : accountEmail;
                    }

                    return {
                        streetAddress: typeof billingAddress.line2 !== 'undefined'
                                && _.isString(billingAddress.line2)
                            ? replaceSingleQuoteCharacter(billingAddress.line1)
                                    + ' ' + replaceSingleQuoteCharacter(billingAddress.line2)
                            : replaceSingleQuoteCharacter(billingAddress.line1),
                        locality: replaceSingleQuoteCharacter(billingAddress.city),
                        postalCode: billingAddress.postalCode,
                        countryCodeAlpha2: billingAddress.countryCode,
                        email: accountEmail,
                        telephone: removeNonDigitCharacters(phone),
                        region: typeof billingAddress.state !== 'undefined'
                            ? replaceSingleQuoteCharacter(billingAddress.state)
                            : ''
                    };
                }
            },

            /**
             * Get line items and amount breakdown
             *
             * @returns {Promise<any>}
             */
            getLineItems: function () {
                return fetch(`/rest/${this.getStoreCode()}/V1/paypal/lineItems`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'x-requested-with': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        method: this.getCode(),
                        cartId: this.getQuoteId()
                    })
                }).then((response) => response.json())
                    .then((cartData) => JSON.parse(cartData));
            }
        });
    }
);

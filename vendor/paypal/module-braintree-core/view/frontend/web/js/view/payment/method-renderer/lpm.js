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
        'Magento_Checkout/js/view/payment/default',
        'ko',
        'underscore',
        'jquery',
        'uiLayout',
        'braintree',
        'braintreeDataCollector',
        'braintreeLpm',
        'PayPal_Braintree/js/form-builder',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/action/select-billing-address',
        'PayPal_Braintree/js/helper/remove-non-digit-characters',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        'mage/translate',
        'mage/calendar',
        'Magento_Customer/js/validation'
    ],
    function (
        Component,
        ko,
        _,
        $,
        layout,
        braintree,
        dataCollector,
        lpm,
        formBuilder,
        messageList,
        selectBillingAddress,
        removeNonDigitCharacters,
        fullScreenLoader,
        quote,
        additionalValidators,
        url,
        $t
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                code: 'braintree_local_payment',
                paymentMethodsAvailable: ko.observable(false),
                paymentMethodNonce: null,
                paymentId: null,
                isPui: ko.observable(false),
                legalText: $t("By clicking on the button, you agree to the <a href='https://www.ratepay.com/legal-payment-terms' target='_blank'>terms of payment</a> and <a href='https://www.ratepay.com/legal-payment-dataprivacy' target='_blank'>performance of a risk check</a> from the payment partner, Ratepay. You also agree to PayPal’s <a href='https://www.paypal.com/us/legalhub/paypal/privacy-full' target='_blank'>privacy statement</a>. If your request to purchase upon invoice is accepted, the purchase price claim will be assigned to Ratepay, and you may only pay Ratepay, not the merchant."),
                template: 'PayPal_Braintree/payment/lpm'
            },

            clickPaymentBtn: function (method) {
                let self = this;

                // If the method is not pay upon invoice then hide the extra fields.
                if (method !== 'pay_upon_invoice') {
                    this.isPui(false);
                }

                if (additionalValidators.validate()) {
                    fullScreenLoader.startLoader();

                    braintree.create({
                        authorization: self.getClientToken()
                    }, function (clientError, clientInstance) {
                        if (clientError) {
                            self.setErrorMsg($t('Unable to initialize Braintree Client.'));
                            fullScreenLoader.stopLoader();
                            return;
                        }
                        dataCollector.create({
                            client: clientInstance,
                            paypal: true,
                        }, function (dataCollectorErr, dataCollectorInstance) {
                            // Stop if there was a problem creating the data collector component.
                            // This could happen if there was a network error or if it's incorrectly
                            // configured.
                            if (dataCollectorErr) {
                                console.error('Error creating data collector:', dataCollectorErr);
                                return;
                            }

                            lpm.create({
                                client: clientInstance,
                                merchantAccountId: self.getMerchantAccountId(),
                                redirectUrl: false
                            }, function (lpmError, lpmInstance) {
                                if (lpmError) {
                                    self.setErrorMsg(lpmError);
                                    fullScreenLoader.stopLoader();
                                    return;
                                }

                                var paymentData = {
                                    amount: self.getAmount(),
                                    currencyCode: self.getCurrencyCode(),
                                    paymentType: method,
                                    // paymentTypeCountryCode
                                    email: self.getCustomerDetails().email,
                                    givenName: self.getCustomerDetails().firstName,
                                    surname: self.getCustomerDetails().lastName,
                                    phone: removeNonDigitCharacters(_.get(self.getCustomerDetails(), 'phone', '')),
                                    // recurrent
                                    // shippingAddressRequired: !quote.isVirtual(),
                                    address: self.getShippingAddress(),
                                    onPaymentStart: function (data, start) {
                                        start();
                                    },
                                    // This is a required option, however it will apparently never be used in the current
                                    // payment flow. Therefore, both values are set to allow the payment flow to continue,
                                    // rather than error out.
                                    fallback: {
                                        url: self.getFallbackUrl(),
                                        buttonText: self.getFallbackButtonText()
                                    }
                                };

                                if (method === 'pay_upon_invoice') {
                                    paymentData = self.attachPuiPaymentData(paymentData, dataCollectorInstance);
                                }

                                lpmInstance.startPayment(paymentData, function (startPaymentError, payload) {
                                    fullScreenLoader.stopLoader();
                                    if (startPaymentError) {
                                        if (method === 'pay_upon_invoice') {
                                            return self.handlePuiError(startPaymentError);
                                        }

                                        switch (startPaymentError.code) {
                                            case 'LOCAL_PAYMENT_POPUP_CLOSED':
                                                self.setErrorMsg($t('Local Payment popup was closed unexpectedly.'));
                                                break;
                                            case 'LOCAL_PAYMENT_WINDOW_OPEN_FAILED':
                                                self.setErrorMsg($t('Local Payment popup failed to open.'));
                                                break;
                                            case 'LOCAL_PAYMENT_WINDOW_CLOSED':
                                                self.setErrorMsg($t('Local Payment popup was closed. Payment cancelled.'));
                                                break;
                                            default:
                                                self.setErrorMsg('Error! ' + startPaymentError);
                                                break;
                                        }
                                    } else {
                                        // Send the nonce to your server to create a transaction
                                        if (method !== 'pay_upon_invoice') {
                                            self.setPaymentMethodNonce(payload.nonce);
                                        }

                                        self.placeOrder();
                                    }
                                });
                            });
                        });
                    });
                }
            },

            getShippingAddress: function () {
                let shippingAddress = quote.shippingAddress();

                if (quote.isVirtual()) {
                    return {
                        countryCode: shippingAddress.countryId
                    };
                }

                return {
                    streetAddress: shippingAddress.street[0],
                    extendedAddress: shippingAddress.street[1],
                    locality: shippingAddress.city,
                    postalCode: shippingAddress.postcode,
                    region: shippingAddress.region,
                    countryCode: shippingAddress.countryId
                };
            },

            getAmount: function () {
                return quote.totals()['base_grand_total'].toFixed(2);
            },

            getBillingAddress: function () {
                const billingAddress = quote.billingAddress();

                return {
                    streetAddress: billingAddress.street[0],
                    extendedAddress: billingAddress.street[1],
                    locality: billingAddress.city,
                    postalCode: billingAddress.postcode,
                    region: billingAddress.region,
                    countryCode: billingAddress.countryId
                };
            },

            getClientToken: function () {
                return window.checkoutConfig.payment[this.getCode()].clientToken;
            },

            getCode: function () {
                return this.code;
            },

            getCurrencyCode: function () {
                return quote.totals()['base_currency_code'];
            },

            getLocale: function () {
                return window.checkoutConfig.payment[this.getCode()].locale.replace('_', '-');
            },

            getCustomerDetails: function () {
                let billingAddress = quote.billingAddress();

                return {
                    firstName: billingAddress.firstname,
                    lastName: billingAddress.lastname,
                    phone: billingAddress.telephone !== null ? billingAddress.telephone : '',
                    email: typeof quote.guestEmail === 'string'
                        ? quote.guestEmail : window.checkoutConfig.customerData.email
                };
            },

            getData: function () {
                let data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method_nonce': this.paymentMethodNonce
                    }
                };

                if (this.isPui()) {
                    data.additional_data.paymentId = this.paymentId
                    data.additional_data.fundingSource = 'pay_upon_invoice'
                }
                data['additional_data'] = _.extend(data['additional_data'], this.additionalData);

                return data;
            },

            getMerchantAccountId: function () {
                return window.checkoutConfig.payment[this.getCode()].merchantAccountId;
            },

            getPaymentMethod: function (method) {
                let methods = this.getPaymentMethods();

                for (let i = 0; i < methods.length; i++) {
                    if (methods[i].method === method) {
                        return methods[i];
                    }
                }
            },

            /**
             * Get allowed local payment methods
             *
             * @returns {*}
             */
            getPaymentMethods: function () {
                return window.checkoutConfig.payment[this.getCode()].allowedMethods;
            },

            /**
             * Get payment icons
             *
             * @returns {*}
             */
            getPaymentMarkSrc: function () {
                return window.checkoutConfig.payment[this.getCode()].paymentIcons;
            },

            /**
             * Get title
             *
             * @returns {*}
             */
            getTitle: function () {
                return window.checkoutConfig.payment[this.getCode()].title;
            },

            /**
             * Get fallback url
             *
             * @returns {String}
             */
            getFallbackUrl: function () {
                return window.checkoutConfig.payment[this.getCode()].fallbackUrl;
            },

            /**
             * Get fallback button text
             * @returns {String}
             */
            getFallbackButtonText: function () {
                return window.checkoutConfig.payment[this.getCode()].fallbackButtonText;
            },

            /**
             * Initialize
             *
             * @returns {*}
             */
            initialize: function () {
                this._super();

                additionalValidators.registerValidator({
                    validate: () => {
                        if (this.isPui()) {
                            const $form = $(`#braintree_lpm_pay_upon_invoice_form`);
                            return $form.validation() && $form.validation('isValid');
                        }

                        return true;
                    }
                });

                return this;
            },

            /**
             * Is payment method active?
             *
             * @returns {boolean}
             */
            isActive: function () {
                let address = quote.billingAddress() || quote.shippingAddress(),
                    methods = this.getPaymentMethods();

                for (let i = 0; i < methods.length; i++) {
                    if (methods[i].countries.includes(address.countryId)) {
                        return true;
                    }
                }

                return false;
            },

            /**
             * Is country and currency valid?
             *
             * @param method
             * @returns {boolean}
             */
            isValidCountryAndCurrency: function (method) {
                let billingAddress = quote.billingAddress(),
                    shippingAddress = quote.shippingAddress(),
                    billingCountryId = billingAddress?.countryId,
                    shippingCountryId = shippingAddress?.countryId,
                    quoteCurrency = quote.totals()['base_currency_code'],
                    quoteGrandTotal = quote.totals()['base_grand_total'],
                    paymentMethodDetails = this.getPaymentMethod(method);

                if (!billingAddress) {
                    this.paymentMethodsAvailable(false);
                    return false;
                }

                if (
                    this.isGrandTotalWithinThreshold(quoteGrandTotal, paymentMethodDetails)
                    && this.isValidCurrency(quoteCurrency, paymentMethodDetails)
                    && this.isValidCountry(billingCountryId, paymentMethodDetails)
                    && (!paymentMethodDetails.checkShipping || this.isValidCountry(shippingCountryId, paymentMethodDetails))
                ) {
                    this.paymentMethodsAvailable(true);
                    return true;
                }

                return false;
            },

            handlePuiError(error) {
                const errorCode = error?.details?.originalError?.details?.originalError?.paymentResource?.errorDetails?.[0]?.issue;

                let message = $t('Could not process your order.');

                if (errorCode === 'PAYMENT_SOURCE_INFO_CANNOT_BE_VERIFIED' || errorCode === 'PAYMENT_SOURCE_DECLINED_BY_PROCESSOR') {
                    if (errorCode === 'PAYMENT_SOURCE_INFO_CANNOT_BE_VERIFIED') {
                        message = $t('The combination of your name and address could not be validated. Please correct your data and try again.');
                    } else {
                        message = $t('It is not possible to use the selected payment method. This decision is based on automated data processing.');
                    }

                    message += ' ' + $t("You can find further information in the <a href='https://www.ratepay.com/en/ratepay-data-privacy-statement/' target='_blank'>Ratepay Data Privacy Statement</a> or you can contact Ratepay using this <a href='https://www.ratepay.com/en/contact/' target='_blank'>contact form</a>.");
                }

                this.messageContainer.addErrorMessage({ message });
                return;
            },

            /**
             * Set error message
             *
             * @param message
             */
            setErrorMsg: function (message) {
                messageList.addErrorMessage({
                    message: message
                });
            },

            /**
             * Set payment method nonce
             *
             * @param nonce
             */
            setPaymentMethodNonce: function (nonce) {
                this.paymentMethodNonce = nonce;
            },

            /**
             * Validate form
             *
             * @param form
             * @returns {*|jQuery}
             */
            validateForm: function (form) {
                return $(form).validation() && $(form).validation('isValid');
            },

            /**
             * Checks the grand total of the quote against the thresolds set for the LPM.
             *
             * @param {float} quoteGrandTotal
             * @param {object} paymentMethodDetails
             * @returns {boolean}
             */
            isGrandTotalWithinThreshold: function (quoteGrandTotal, paymentMethodDetails) {
                const { threshold } = paymentMethodDetails;

                if (Object.hasOwn(threshold, 'min') && quoteGrandTotal < threshold.min) {
                    return false;
                }

                if (Object.hasOwn(threshold, 'max') && quoteGrandTotal > threshold.max) {
                    return false;
                }

                return true;
            },

            /**
             * Checks that the quote currency matches with the acceptable currencies for a given LPM.
             *
             * @param {string} quoteCurrency
             * @param {object} paymentMethodDetails
             * @returns {boolean}
             */
            isValidCurrency: function (quoteCurrency, paymentMethodDetails) {
                const { currencies } = paymentMethodDetails;

                if (!currencies.length) {
                    return true;
                }

                return currencies.some((currency) => quoteCurrency === currency);
            },

            /**
             * Checks that the quote country matches with the acceptable currencies for a given LPM.
             *
             * @param {string} quoteCountry
             * @param {object} paymentMethodDetails
             * @returns {boolean}
             */
            isValidCountry: function (quoteCountry, paymentMethodDetails) {
                const { countries } = paymentMethodDetails;

                if (!countries.length) {
                    return true;
                }

                return countries.some((country) => quoteCountry === country);
            },

            /**
             * Add Pay Upon Invoice payment data.
             *
             * @see https://braintree.github.io/braintree-web/current/LocalPayment.html#~StartPaymentPayUponInvoiceOptions
             *
             * @param {*} paymentData
             */
            attachPuiPaymentData: function (paymentData, dataCollectorInstance) {
                const discountAmount = quote.totals()['base_discount_amount']
                    ? Math.abs(quote.totals()['base_discount_amount']).toFixed(2)
                    : "0.00";

                return Object.assign(paymentData, {
                    shippingAmount: quote.totals()['base_shipping_amount']?.toFixed(2) || "0.00",
                    discountAmount,
                    phoneCountryCode: '49',
                    billingAddress: this.getBillingAddress(),
                    birthDate: $('#braintree-lpm-pui-dob').val(),
                    locale: this.getLocale(),
                    countryCode: 'DE',
                    customerServiceInstructions: 'Customer service phone is +49 6912345678.',
                    correlationId: dataCollectorInstance.rawDeviceData.correlation_id,
                    lineItems: this.getLineItems(),
                    onPaymentStart: function (data) {
                        this.paymentId = data.paymentId;
                    }.bind(this)
                })
            },

            getDob: function (element) {
                const html = window.checkoutConfig.payment[this.getCode()].dob;
                const dobConfig = window.checkoutConfig.payment[this.getCode()].dobConfig;

                element.innerHTML = html;
                dobConfig.altField = '#braintree-lpm-pui-dob';
                dobConfig.altFormat = 'yy-mm-dd';
                $("#dob").calendar(dobConfig);
            },

            getLineItems: function () {
                const quoteItems = quote.totals().items;
                return quoteItems.map((quoteItem) => ({
                    category: 'PHYSICAL_GOODS',
                    name: quoteItem.name,
                    quantity: quoteItem.qty.toString(),
                    unitAmount: Number(quoteItem.base_price).toFixed(2),
                    unitTaxAmount: Number(quoteItem.base_tax_amount).toFixed(2),
                }));
            },

            /**
             * Create child message renderer component
             *
             * @returns {Component} Chainable.
             */
            createMessagesComponent: function () {
                // Override the default message container to be able to render HTML errors.
                    var messagesComponent = {
                        parent: this.name,
                        name: this.name + '.messages',
                        displayArea: 'messages',
                        component: 'Magento_Ui/js/view/messages',
                        config: {
                            messageContainer: this.messageContainer,
                            removeAll: (data, event) => {
                                if (event.target.tagName === 'A') {
                                    return true;
                                }

                                this.messageContainer.clear();
                            }
                        },
                        template: 'PayPal_Braintree/html-messages',
                    };

                    layout([messagesComponent]);

                    return this;
            },
        });
    }
);

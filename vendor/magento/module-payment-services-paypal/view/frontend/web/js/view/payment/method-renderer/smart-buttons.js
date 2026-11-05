/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2021 Adobe
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
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'underscore',
    'mageUtils',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/model/app-switch-data',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/smart-buttons',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Ui/js/model/messageList',
    'uiRegistry',
    'Magento_Customer/js/customer-data'
], function (
    Component,
    $,
    _,
    utils,
    quote,
    fullScreenLoader,
    $t,
    appSwitchDataModel,
    SmartButtons,
    additionalValidators,
    setBillingAddressAction,
    globalMessageList,
    registry,
    customerData
) {
    'use strict';

    return Component.extend({
        defaults: {
            buttonContainerId: 'smart-buttons-${ $.uid }',
            template: 'Magento_PaymentServicesPaypal/payment/smart-buttons',
            isAvailable: false,
            isButtonsRendered: false,
            paymentsOrderId: null,
            paymentSource: '',
            requestProcessingError: $t('Error happened when processing the request. Please try again later.'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            paymentTypeIconUrl: window.checkoutConfig.payment['payment_services_paypal_smart_buttons'].paymentTypeIconUrl, // eslint-disable-line max-len
            paymentTypeIconTitle: $t('Pay with PayPal')
        },

        /**
         * @inheritdoc
         */
        initialize: function (config) {
            config.uid = utils.uniqueid();
            this._super();
            this.initSmartButtons();

            return this;
        },

        /**
         * Initialize observables
         *
         * @returns {Component} Chainable.
         */
        initObservable: function () {
            this._super().observe('isAvailable isButtonsRendered');

            return this;
        },

        /**
         * Create smart buttons instance.
         */
        initSmartButtons: function () {
            const methodConfig = window.checkoutConfig.payment[this.getCode()],
                getSmartButtonsRenderConfig = (defaultSmartButtonsRenderConfig) =>
                    _.extend({}, defaultSmartButtonsRenderConfig, {
                        onStart: this.onStart.bind(this),
                        onButtonClick: this.onButtonClick.bind(this),
                        onSuccess: this.onPaymentSuccess.bind(this),
                        getCartId: this.getCartId.bind(this)
                    }),
                addErrorMessage = (message) => {
                    this.messageContainer.addErrorMessage({ message });
                };

            this.smartButtons = new SmartButtons({
                location: 'CHECKOUT',
                placeOrderUrl: null,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: methodConfig['paymentSdkParams'],
                getSmartButtonsRenderConfig: getSmartButtonsRenderConfig,
                showLoader: this.showFullScreenLoader.bind(this),
                isButtonDisabled: !quote.billingAddress(),
                logCustomerErrorMessage: addErrorMessage
            });

            if (this.totalsSubscription) {
                this.totalsSubscription.dispose();
            }
            this.totalsSubscription = quote.totals.subscribe(this.onTotalsChanged, this);
            this.onTotalsChanged(quote.totals());

            if (this.billingAddressSubscription) {
                this.billingAddressSubscription.dispose();
            }

            this.billingAddressSubscription = quote.billingAddress.subscribe((newAddress) => {
                this.smartButtons.isButtonDisabled(!newAddress);
            });
        },

        /**
         * @inheritdoc
         */
        destroy: function (skipUpdate) {
            if (this.totalsSubscription) {
                this.totalsSubscription.dispose();
                this.totalsSubscription = null;
            }
            if (this.billingAddressSubscription) {
                this.billingAddressSubscription.dispose();
                this.billingAddressSubscription = null;
            }
            if (this.smartButtons) {
                this.smartButtons.disposePayLaterMessage();
            }

            this._super(skipUpdate);
        },

        onTotalsChanged: function (totals) {
            this.smartButtons.updatePayLaterMessagePrice(totals ? {
                currency: totals.base_currency_code,
                value: Number(totals.base_grand_total)
            } : null);
        },

        /**
         * Get method code
         *
         * @return {String}
         */
        getCode: function () {
            return 'payment_services_paypal_smart_buttons';
        },

        /**
         * Get method data
         *
         * @return {Object}
         */
        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'payments_order_id': this.paymentsOrderId,
                    'payment_source': this.paymentSource
                }
            };
        },

        /**
         * Render buttons
         */
        afterRender: function () {
            this.smartButtons.renderSmartButtons()
                .then(this.isAvailable.bind(this, true))
                .catch(this.isAvailable.bind(this, false))
                .finally(this.isButtonsRendered.bind(this, true));
        },

        /**
         * @inheritdoc
         */
        validate: function () {
            var isShippingValid = true,
                source, shippingAddress;

            if (!this._super()) {
                return false;
            }
            source = registry.get('checkoutProvider');
            shippingAddress = registry.get('index = shippingAddress');

            if (source && shippingAddress) {
                source.set('params.invalid', false);
                if (quote.billingAddress() === null) {
                    this.triggerBillingValidation(source);
                }

                if (!quote.isVirtual() && quote.shippingMethod()
                    && quote.shippingMethod()['method_code'] !== 'pickup') {
                    isShippingValid = shippingAddress.validateShippingInformation();
                }

                return isShippingValid && !source.get('params.invalid');
            }

            return true;
        },

        /**
         * Trigger billing address validation
         *
         * @param {Object} source
         */
        triggerBillingValidation: function (source) {
            var dataScope = `billingAddress${ window.checkoutConfig.displayBillingOnPaymentMethod ?
                this.getCode() : 'shared'}`;

            source.trigger(`${ dataScope }.data.validate`);

            if (source.get(`${dataScope}.custom_attributes`)) {
                source.trigger(`${dataScope}.custom_attributes.data.validate`);
            }
        },

        /**
         * Returns masked cart id.
         */
        getCartId: async function () {
            const cart = customerData.get('cart')();

            if (cart && cart.masked_quote_id) {
                return cart.masked_quote_id;
            }

            throw new Error(this.requestProcessingError);
        },

        /**
         * Validate forms and show the PayPal payment sheet when valid.
         *
         * @param {Function} showPaymentSheet
         */
        onButtonClick: function (showPaymentSheet) {
            if (this.validate() && additionalValidators.validate() && this.isPlaceOrderActionAllowed()) {
                if (window.checkoutConfig?.checkoutAgreements?.isEnabled) {
                    const agreementsInputPath = '.payment-method._active div.checkout-agreements input';

                    $(agreementsInputPath).each(function (index, element) {
                        const termsData = appSwitchDataModel.getData('terms') || {};

                        termsData[element.id] = element.value;
                        appSwitchDataModel.setData('terms', termsData);
                    });
                }

                const reCaptcha = $('.g-recaptcha:visible'),
                    reCaptchaId = reCaptcha && reCaptcha.last().attr('id');

                if (reCaptchaId) {
                    const reCaptchaToken = $(`#${reCaptchaId} [name="g-recaptcha-response"]`).val();

                    appSwitchDataModel.setData('recaptcha', reCaptchaToken);
                }

                showPaymentSheet();
            }
        },

        onStart: async function () {
            try {
                await setBillingAddressAction(globalMessageList);
            } catch (error) {
                throw error;
            }
        },

        /** Event handler for Smart Buttons payment succeeded. */
        onPaymentSuccess: function ({ mpOrderId }) {
            this.setAppSwitchResumeData();
            this.paymentsOrderId = mpOrderId;
            this.paymentSource = this.paymentSource || this.smartButtons.paymentSource || 'paypal';

            return this.placeOrderAndGetPromise();
        },

        /**
         * Restore checkout data after returning from PayPal App Switch.
         */
        setAppSwitchResumeData: function () {
            if (window.checkoutConfig?.checkoutAgreements?.isEnabled) {
                const terms = appSwitchDataModel.getData('terms');

                if (terms) {
                    Object.keys(terms).forEach((term) => {
                        $(`#${term}`).prop('checked', terms[term] === '1');
                    });
                }
            }

            const reCaptcha = $('.g-recaptcha:visible'),
                reCaptchaId = reCaptcha && reCaptcha.last().attr('id');

            if (reCaptchaId) {
                const reCaptchaToken = appSwitchDataModel.getData('recaptcha');

                $(`#${reCaptchaId} [name="g-recaptcha-response"]`).val(reCaptchaToken);
            }

            appSwitchDataModel.clearData();
        },

        /** Initiates order place action and returns promise that resolves when order is successfully placed. */
        placeOrderAndGetPromise: function () {
            if (!this.placeOrder()) {
                return Promise.reject(new Error('Place order not allowed.'));
            }
            return new Promise((resolve, reject) => {
                this._placeOrderDeferredObject.done(resolve);
                this._placeOrderDeferredObject.fail(reject);
            });
        },

        /** Override to cache place order deferred object. */
        getPlaceOrderDeferredObject: function () {
            this._placeOrderDeferredObject = this._super();
            return this._placeOrderDeferredObject;
        },

        /** Shows/hides full-screen loader */
        showFullScreenLoader: function (show) {
            if (show) {
                fullScreenLoader.startLoader();
            } else {
                fullScreenLoader.stopLoader();
            }
        }
    });
});

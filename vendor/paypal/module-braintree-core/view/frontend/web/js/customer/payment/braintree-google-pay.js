define([
    'uiComponent',
    'jquery',
    'ko',
    'mage/translate',
    'PayPal_Braintree/js/googlepay/button',
    'PayPal_Braintree/js/googlepay/api',
    'PayPal_Braintree/js/googlepay/model/parsed-response',
    'PayPal_Braintree/js/googlepay/model/payment-data',
    'PayPal_Braintree/js/view/payment/adapter',
], function (
    Component,
    $,
    ko,
    $t,
    button,
    buttonApi,
    parsedResponseModel,
    paymentDataModel,
    braintreeMainAdapter
) {
    'use strict';

    return Component.extend({
        clientToken: null,
        uiConfig: null,
        paymentMethodNonce: null,
        api: null,
        id: null,

        viewModel: {
            paymentMethodCode: 'braintree_googlepay',
            errorMessage: ko.observable()
        },

        /**
         * @inheritDoc
         */
        initialize: function (uiConfig) {
            this._super();
            this.api = new buttonApi();

            // Force remove the onPaymentDataChanged callback as it's not needed here.
            this.api.onPaymentDataChanged = false;
            this.api.startPlaceOrder = this.startPlaceOrder.bind(this);

            this.id = uiConfig.id;

            /* Add client token & environment to 3DS Config */
            uiConfig.threeDSecure.clientToken = uiConfig.clientToken;
            uiConfig.threeDSecure.environment = uiConfig.environment;

            this.api.setEnvironment(uiConfig.environment);
            this.api.setCurrencyCode(uiConfig.currencyCode);
            this.api.setClientToken(uiConfig.clientToken);
            this.api.setMerchantId(uiConfig.merchantId);
            this.api.setActionSuccess(uiConfig.actionSuccess);
            this.api.setAmount(uiConfig.amount);
            this.api.setCardTypes(uiConfig.cardTypes);
            this.api.setBtnColor(uiConfig.btnColor);
            this.api.setThreeDSecureValidatorConfig(uiConfig.threeDSecure);
            this.api.setStoreCode(uiConfig.storeCode);
            this.api.setQuoteId(uiConfig.quoteId);
            this.api.setSkipReview(uiConfig.skipOrderReviewStep);
            this.api.setPriceIncludesTax(uiConfig.priceIncludesTax);
            this.api.setElement(this.element);

            const paymentDataRequest = this.api.getPaymentRequest();
            this.api.getPaymentRequest = () => this.getPaymentRequest(paymentDataRequest);

            return this;
        },

        setup: function () {
            this.element = $(`#${this.id}`);

            button.init(
                this.element,
                this.api
            );
        },

        teardown: function () {
            const googlePayInstance = braintreeMainAdapter.getGooglePayInstance();

            if (googlePayInstance) {
                try {
                    googlePayInstance.teardown();
                } catch {}
            }

            this.viewModel.errorMessage(false);
            this.element.empty();
        },

        /**
         * Override the default getPaymentRequest to remove the callback intents for shipping.
         *
         * @param {object} paymentDataRequest
         */
        getPaymentRequest: function (paymentDataRequest) {
            paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
            paymentDataRequest.shippingOptionRequired = false;

            return paymentDataRequest;
        },

        /**
         * Place the order
         */
        startPlaceOrder: function (paymentData) {
            return new Promise((resolve) => {
                const googlePaymentInstance = braintreeMainAdapter.getGooglePayInstance();
                googlePaymentInstance.parseResponse(paymentData).then(function (result) {
                    paymentDataModel.setPaymentMethodData(_.get(
                        paymentData,
                        'paymentMethodData',
                        null
                    ));
                    paymentDataModel.setEmail(_.get(paymentData, 'email', ''));
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

                    this.email = paymentDataModel.getEmail();
                    this.paymentMethodNonce = parsedResponseModel.getNonce();
                    this.creditCardBin = parsedResponseModel.getBin();

                    if (parsedResponseModel.getIsNetworkTokenized() === false) {
                        /* Add 3D Secure verification to payment & validate payment for non network tokenized cards */
                        this.api.addThreeDSecureValidator();

                        this.api.validatorManager.validate(this, () => {
                            this.completeVault(this.paymentMethodNonce);
                        },() => {
                            this.paymentMethodNonce = null;
                            this.creditCardBin = null;
                        });

                        resolve({
                            transactionState: 'SUCCESS',
                        });
                    } else {
                        resolve({
                            transactionState: 'SUCCESS',
                        });
                        this.completeVault(parsedResponseModel.getNonce());
                    }
                }.bind(this));
            });
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

        completeVault: function (nonce) {
            fetch('/rest/default/V1/braintree/mine/payment/vault', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'x-requested-with': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    payment: {
                        payment_method_code: 'braintree_googlepay',
                        payment_method_nonce: nonce,
                        device_data: braintreeMainAdapter.deviceData
                    }
                }),
            }).then((response) => {
                if (!response.ok) {
                    throw new Error($t('Please try again with another form of payment.'));
                }

                return response.json();
            }).then(() => {
                window.location.reload();
            }).catch((error) => {
                $('body').trigger('processStop');
                this.viewModel.errorMessage(error.message);
            });
        }
    });
});

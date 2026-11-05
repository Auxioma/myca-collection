/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'jquery',
    'underscore',
    'knockout',
    'uiComponent',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_PaymentServicesPaypal/js/helpers/map-payment-sdk-error-message',
    'Magento_PaymentServicesPaypal/js/helpers/map-location-to-pagetype',
    'paymentSdkLoader',
    'Magento_Customer/js/customer-data',
    'mage/translate',
], function (
    $,
    _,
    ko,
    Component,
    ResponseError,
    mapPaymentSdkErrorMessage,
    mapLocationToPageType,
    paymentSdkLoader,
    customerData,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            location: '', // values should follow those defined in PaymentLocation GraphQL schema
            placeOrderUrl: '',
            cancelUrl: '',
            buttonContainerId: '',
            isButtonDisabled: false,
            requestProcessingError: $t('Something went wrong with your request. Please try again later.'),
            paymentMethodValidationError: $t('Your payment was not successful. Please try again later.'),
            paymentSdkReady: null,
            paymentSdkParams: {
                paymentsSDKUrl: undefined, // TODO: Rename to preferredSdkUrl (also in PaymentsSDKConfigProvider)
                paymentsSDKFallbackUrl: undefined, // TODO: Rename to fallbackSdkUrl (also in PaymentsSDKConfigProvider)
                graphQLEndpointUrl: undefined,
                commerceVersion: undefined,
                oauthToken: undefined, // TODO: Rename to customerToken (also in PaymentsSDKConfigProvider)
                isGuestCustomer: undefined,
                storeViewCode: undefined,
            },
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();

            const loader = paymentSdkLoader()
                .withPreferredUrl(this.paymentSdkParams.paymentsSDKUrl)
                .withFallbackUrl(this.paymentSdkParams.paymentsSDKFallbackUrl)
                .withGraphqlEndpoint(this.paymentSdkParams.graphQLEndpointUrl)
                .withCommerceVersion(this.paymentSdkParams.commerceVersion)
                .withCustomerToken(this.paymentSdkParams.oauthToken)
                .withIsGuestCustomer(this.paymentSdkParams.isGuestCustomer)
                .forStoreView(this.paymentSdkParams.storeViewCode)
                .withPaymentNamespace(this.location);

            this.paymentSdkReady = loader.load();
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super().observe('isButtonDisabled');
            return this;
        },

        /** Renders Apple Pay button or throws error if Apple Pay unavailable. */
        renderApplePayButton: async function () {
            const sdk = await this.paymentSdkReady;

            if (!sdk.Payment.ApplePay.isAvailable()) {
                throw new Error("Apple Pay is not available.");
            }

            const onErrorRef = {
                current: this._defaultOnErrorHandler.bind(this)
            }

            const renderConfig = this.getApplePayRenderConfig({
                container: `#${this.buttonContainerId}`,
                buttonLanguage: { locale: window.LOCALE },
                useApplePayCouponCodes: this.location !== "CHECKOUT" && this.location !== "CART",
                useApplePayShippingAddress: this.location !== "CHECKOUT",
                useApplePayBillingAddress: this.location !== "CHECKOUT",
                getCartId: this._defaultCartIdGetter.bind(this),
                onButtonClick: this._defaultOnButtonClickHandler.bind(this),
                onSuccess: () => {
                    const promise = this._defaultOnSuccessHandler();
                    promise.catch(onErrorRef.current);
                },
                onError: this._defaultOnErrorHandler.bind(this),
                onCancel: this._defaultOnCancelHandler.bind(this),
                disabled: this.isButtonDisabled(),
            });

            onErrorRef.current = renderConfig.onError;

            const instance = await sdk.Payment.ApplePay.render(renderConfig);
            this.isButtonDisabled.subscribe(instance.setDisabled, instance);
        },

        /** Override this to extend the default Apple Pay render config. */
        getApplePayRenderConfig: function (defaultApplePayRenderConfig) {
            return defaultApplePayRenderConfig;
        },

        /** Called when commerce order successfully placed, immediately before redirecting. */
        onOrderPlaced: function () {
        },

        /** Called immediately before the payment sheet is triggered to open. */
        onPaymentSheetOpen: function () {
        },

        /** Override this to perform validation on button click. Return false to prevent payment sheet from showing. */
        validate: function () {
            return true;
        },

        /** Show/hide full-screen loader. Override this for custom loader. */
        showLoader: function (show) {
            const event = show ? 'processStart' : 'processStop';
            $('body').trigger(event);
        },

        /** Log user-facing error message. Override this for custom error message logging. */
        logCustomerErrorMessage: function (message) {
            customerData.set('messages', {
                messages: [{type: "error", text: message}],
                data_id: Math.floor(Date.now() / 1000),
            });
        },

        /** Refresh all user-facing messages. Override this for custom customer message reloading. */
        refreshCustomerMessages: function () {
            customerData.reload(["messages"]);
        },

        ///////////////////////////////////////
        /// Default ApplePay event handlers ///
        ///////////////////////////////////////

        _defaultCartIdGetter: async function () {
            const cart = customerData.get("cart")();
            if (!cart || !cart["masked_quote_id"]) {
                throw new Error("No 'cart' customer data or no 'masked_quote_id'.")
            }
            return cart["masked_quote_id"];
        },

        _defaultOnButtonClickHandler: function (showPaymentSheet) {
            if (this.validate()) {
                this.showLoader(true);
                this.refreshCustomerMessages();
                if (typeof this.onPaymentSheetOpen === "function") {
                    this.onPaymentSheetOpen();
                }
                showPaymentSheet();
            }
        },

        _defaultOnSuccessHandler: async function () {
            const body = new FormData();
            body.append("form_key", $.cookie("form_key") || window.FORM_KEY);
            body.append("location", mapLocationToPageType(this.location) || "undefined");

            const response = await fetch(this.placeOrderUrl, { method: "POST", body });
            const { redirectUrl, error } = await response.json();

            if (error) {
                this.refreshCustomerMessages();
                throw new ResponseError(error);
            }

            if (typeof this.onOrderPlaced === "function") {
                this.onOrderPlaced();
            }

            if (redirectUrl) {
                window.location.replace(redirectUrl);
            }
        },

        _defaultOnErrorHandler: function (error) {
            this.showLoader(false);
            const localizedSdkMessage = mapPaymentSdkErrorMessage(error);
            if (error instanceof ResponseError) {
                this.logCustomerErrorMessage(error.message);
            } else if (localizedSdkMessage) {
                this.logCustomerErrorMessage(localizedSdkMessage);
            } else if (error?.debug_id) {
                this.logCustomerErrorMessage(this.paymentMethodValidationError);
            } else {
                this.logCustomerErrorMessage(this.requestProcessingError);
            }
        },

        _defaultOnCancelHandler: function () {
            this.showLoader(false);
            if (this.cancelUrl) {
                customerData.invalidate(['cart']);
                window.location = this.cancelUrl;
            }
        }
    });
});

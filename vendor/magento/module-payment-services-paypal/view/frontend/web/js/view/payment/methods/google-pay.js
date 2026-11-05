/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

/* eslint-disable no-undef */
define([
    'jquery',
    'underscore',
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
            reviewPageUrl: '',
            cancelUrl: '',
            buttonContainerId: '',
            isButtonDisabled: false,
            skipReviewPage: false,
            requestProcessingError: $t('Error happened when processing the request. Please try again later.'),
            paymentMethodValidationError: $t('Your payment was not successful. Try again.'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
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

        /** Renders Google Pay button or throws error if Google Pay unavailable. */
        renderGooglePayButton: async function () {
            const sdk = await this.paymentSdkReady;

            if (!sdk.Payment.GooglePay.isAvailable()) {
                throw new Error("Google Pay is not available.");
            }

            const renderConfig = this.getGooglePayRenderConfig({
                container: `#${this.buttonContainerId}`,
                buttonLanguage: { locale: window.LOCALE },
                useGooglePayShippingAddress: this.location !== "CHECKOUT",
                useGooglePayBillingAddress: this.location !== "CHECKOUT",
                useGooglePayCouponCodes: this.location !== "CHECKOUT" && this.location !== "CART",
                getCartId: this._defaultCartIdGetter.bind(this),
                getCartDetails: this._defaultCartDetailsGetter.bind(this),
                onButtonClick: this._defaultOnButtonClickHandler.bind(this),
                onSuccess: this._defaultOnSuccessHandler.bind(this),
                onError: this._defaultOnErrorHandler.bind(this),
                onCancel: this._defaultOnCancelHandler.bind(this),
                disabled: this.isButtonDisabled()
            });

            const instance = await sdk.Payment.GooglePay.render(renderConfig);
            this.isButtonDisabled.subscribe(instance.setDisabled, instance);
        },

        /** Override this to extend the default Google Pay render config. */
        getGooglePayRenderConfig: function (defaultGooglePayRenderConfig) {
            return defaultGooglePayRenderConfig;
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
            customerData.reload(['messages']);
        },

        ///////////////////////////////////////
        /// Default GooglePay event handlers ///
        ///////////////////////////////////////

        _defaultCartIdGetter: async function () {
            const cart = customerData.get('cart')();
            if (!cart || !cart['masked_quote_id']) {
                throw new Error('No \'cart\' customer data or no \'masked_quote_id\'.')
            }
            return cart['masked_quote_id'];
        },

        _defaultCartDetailsGetter: function () {
            const cart = customerData.get("cart")() || {};
            return { isVirtual: cart.is_virtual };
        },

        _defaultOnButtonClickHandler: function (showPaymentSheet) {
            if (this.validate()) {
                this.showLoader(true);
                this.refreshCustomerMessages();
                if (typeof this.onPaymentSheetOpen === 'function') {
                    this.onPaymentSheetOpen();
                }
                showPaymentSheet();
            }
        },

        _defaultOnSuccessHandler: async function () {
            if (!this.skipReviewPage) {
                window.location = this.reviewPageUrl;
                return;
            }

            const body = new FormData();
            body.append('form_key', $.mage.cookies.get('form_key'));
            body.append('location', mapLocationToPageType(this.location) || 'undefined');

            const response = await fetch(this.placeOrderUrl, { method: 'POST', body });
            const { redirectUrl, error } = await response.json();

            if (error) {
                this.refreshCustomerMessages();
                throw new ResponseError(error);
            }

            if (typeof this.onOrderPlaced === 'function') {
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

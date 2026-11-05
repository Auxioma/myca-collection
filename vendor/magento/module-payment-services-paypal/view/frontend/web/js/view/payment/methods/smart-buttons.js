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
    'jquery',
    'uiComponent',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_PaymentServicesPaypal/js/helpers/map-location-to-pagetype',
    'paymentSdkLoader',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function ($, Component, ResponseError, mapLocationToPageType, paymentSdkLoader, customerData, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            syncQuoteUrl: '',
            restoreQuoteUrl: '',
            cancelUrl: '',
            canSkipReviewForPayPal: false,
            location: '',
            placeOrderUrl: '',
            buttonContainerId: '',
            isButtonDisabled: false,
            requestProcessingError: $t('Something went wrong with your request. Please try again later.'),
            paymentMethodValidationError: $t('Your payment was not successful. Please try again later.'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            paymentSource: '',
            payLaterMessagePrice: null,
            paymentSdkReady: null,
            paymentSdkParams: {
                paymentsSDKUrl: undefined,
                paymentsSDKFallbackUrl: undefined,
                graphQLEndpointUrl: undefined,
                commerceVersion: undefined,
                oauthToken: undefined,
                isGuestCustomer: undefined,
                storeViewCode: undefined
            }
        },

        /** @inheritdoc */
        initialize: function () {
            this.smartButtonsInstance = null;
            this.payLaterMessageInstance = null;
            this.payLaterMessageRenderVersion = 0;
            this.isButtonDisabledSubscription = null;
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

        /** Renders Smart Buttons or throws if unavailable. */
        renderSmartButtons: async function () {
            this.disposeSmartButtons();

            const sdk = await this.paymentSdkReady;

            if (!sdk.Payment.PayPalButtons.isAvailable()) {
                throw new Error('Smart Buttons are not available.');
            }

            let renderConfig = this.getSmartButtonsRenderConfig({
                container: `#${this.buttonContainerId}`,
                onStart: this._defaultOnStartHandler.bind(this),
                getCartId: this._defaultCartIdGetter.bind(this),
                onButtonClick: this._defaultOnButtonClickHandler.bind(this),
                onSuccess: this._defaultOnSuccessHandler.bind(this),
                onError: this._defaultOnErrorHandler.bind(this),
                onCancel: this._defaultOnCancelHandler.bind(this),
                disabled: this.isButtonDisabled()
            });

            this.smartButtonsInstance = await sdk.Payment.PayPalButtons.render(renderConfig);
            this.isButtonDisabledSubscription = this.isButtonDisabled.subscribe(
                this.smartButtonsInstance.setDisabled,
                this.smartButtonsInstance
            );

            await this.renderPayLaterMessage(
                sdk.Payment.PayPalButtons.PayLaterMessage,
                renderConfig.container
            );

            return this.smartButtonsInstance;
        },

        /** Render Pay Later messaging without affecting successfully rendered Smart Buttons. */
        renderPayLaterMessage: async function (facade, container) {
            const price = this.payLaterMessagePrice,
                renderVersion = ++this.payLaterMessageRenderVersion,
                renderConfig = {
                    container: container
                };
            let instance;

            if (price !== null) {
                renderConfig.price = price;
            }

            try {
                if (!facade || !facade.isAvailable()) {
                    return;
                }

                instance = await facade.render(renderConfig);

                if (this.payLaterMessageRenderVersion !== renderVersion) {
                    instance.teardown();
                    return;
                }

                this.payLaterMessageInstance = instance;
                if (typeof instance.setPrice === 'function') {
                    instance.setPrice(this.payLaterMessagePrice);
                }
            } catch (error) {
                console.error('Unable to render Pay Later message.', error);
            }
        },

        /** Update the current Pay Later message price. */
        updatePayLaterMessagePrice: function (price) {
            const isValid = price && typeof price.currency === 'string' && price.currency.trim() !== '' &&
                    typeof price.value === 'number' && Number.isFinite(price.value) && price.value >= 0,
                instance = this.payLaterMessageInstance,
                nextPrice = isValid ? {
                    currency: price.currency.trim(),
                    value: price.value
                } : null,
                currentPrice = this.payLaterMessagePrice;

            if (currentPrice === nextPrice || (currentPrice && nextPrice &&
                currentPrice.currency === nextPrice.currency && currentPrice.value === nextPrice.value)) {
                return;
            }

            this.payLaterMessagePrice = nextPrice;

            if (!instance || typeof instance.setPrice !== 'function') {
                return;
            }

            try {
                instance.setPrice(this.payLaterMessagePrice);
            } catch (error) {
                console.error('Unable to update Pay Later message price.', error);
            }
        },

        /** Dispose the current Pay Later message instance. */
        disposePayLaterMessage: function () {
            const instance = this.payLaterMessageInstance;

            this.payLaterMessageInstance = null;
            this.payLaterMessageRenderVersion += 1;

            if (!instance || typeof instance.teardown !== 'function') {
                return;
            }

            try {
                instance.teardown();
            } catch (error) {
                console.error('Unable to tear down Pay Later message.', error);
            }
        },

        /** Dispose the current Smart Buttons instance and its Knockout subscription. */
        disposeSmartButtons: function () {
            const subscription = this.isButtonDisabledSubscription,
                instance = this.smartButtonsInstance;

            this.isButtonDisabledSubscription = null;
            this.smartButtonsInstance = null;
            this.disposePayLaterMessage();

            try {
                if (subscription) {
                    subscription.dispose();
                }
            } finally {
                if (!instance) {
                    return;
                }

                if (typeof instance.teardown === 'function') {
                    instance.teardown();
                } else if (typeof instance.close === 'function') {
                    instance.close();
                }
            }
        },

        /** @inheritdoc */
        destroy: function (skipUpdate) {
            try {
                this.disposeSmartButtons();
            } finally {
                this._super(skipUpdate);
            }
        },

        /** Override this to extend default Smart Buttons render config. */
        getSmartButtonsRenderConfig: function (defaultSmartButtonsRenderConfig) {
            return defaultSmartButtonsRenderConfig;
        },

        /** Called when commerce order successfully placed, immediately before redirecting. */
        onOrderPlaced: function () {
        },

        /** Called immediately before the payment sheet is triggered to open. */
        onPaymentSheetOpen: function () {
        },

        /** Override this to perform validation on button click. */
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
                messages: [{type: 'error', text: message}],
                data_id: Math.floor(Date.now() / 1000)
            });
        },

        /** Refresh all user-facing messages. Override this for custom customer message reloading. */
        refreshCustomerMessages: function () {
            customerData.reload(['messages']);
        },

        /**
         * Sync the approved PayPal order with the quote before rendering the review page.
         *
         * @return {Promise<void>}
         */
        syncQuoteForReview: function () {
            let orderData = new FormData();

            orderData.append('form_key', $.mage.cookies.get('form_key'));
            orderData.append('location', mapLocationToPageType(this.location) || 'undefined');

            return fetch(this.syncQuoteUrl, {
                method: 'POST',
                headers: {},
                body: orderData,
                credentials: 'same-origin'
            }).then((response) => {
                return response.json();
            }).then((response) => {
                if (!response.success || !response.redirectUrl) {
                    throw new Error(this.requestProcessingError);
                }

                window.location = response.redirectUrl;
            });
        },

        _defaultOnStartHandler: function () {},

        _defaultCartIdGetter: async function () {
            const cart = customerData.get('cart')();

            if (!cart || !cart['masked_quote_id']) {
                throw new Error('No \'cart\' customer data or no \'masked_quote_id\'.');
            }
            return cart['masked_quote_id'];
        },

        _defaultOnButtonClickHandler: function (showPaymentSheet) {
            if (!this.validate()) {
                return;
            }

            this.showLoader(true);
            this.refreshCustomerMessages();
            if (typeof this.onPaymentSheetOpen === 'function') {
                this.onPaymentSheetOpen();
            }

            showPaymentSheet();
        },

        /**
         * Get place order body
         *
         * @returns {FormData}
         */
        getPlaceOrderBody: function () {
            const body =  new FormData();

            body.append('form_key', $.mage.cookies.get('form_key'));
            body.append('location', mapLocationToPageType(this.location) || 'undefined');

            return body;
        },

        _defaultOnSuccessHandler: async function () {
            let redirectInitiated = false;

            try {
                if (!this.canSkipReviewForPayPal) {
                    return await this.syncQuoteForReview();
                }

                const response = await fetch(this.placeOrderUrl, { method: 'POST', body: this.getPlaceOrderBody() }),
                    { redirectUrl, error } = await response.json();

                if (error) {
                    this.refreshCustomerMessages();
                    throw new ResponseError(error);
                }

                customerData.invalidate(['cart']);

                if (typeof this.onOrderPlaced === 'function') {
                    this.onOrderPlaced();
                }

                if (redirectUrl) {
                    window.location.replace(redirectUrl);
                    redirectInitiated = true;
                }
            } finally {
                if (!redirectInitiated) {
                    this.showLoader(false);
                }
            }
        },

        _defaultOnCancelHandler: async function () {
            try {
                await this.restoreQuoteAfterAbortedPayment();
            } catch (error) {
                console.error(error);
                this.logCustomerErrorMessage(this.requestProcessingError);
            } finally {
                this.showLoader(false);
                if (this.cancelUrl) {
                    customerData.invalidate(['cart']);
                    window.location = this.cancelUrl;
                }
            }
        },

        _defaultOnErrorHandler: async function (error) {
            try {
                await this.restoreQuoteAfterAbortedPayment();
            } catch (restoreError) {
                console.error(restoreError);
                this.showLoader(false);
                this.logCustomerErrorMessage(this.requestProcessingError);
                return;
            }

            this.showLoader(false);
            if (error instanceof ResponseError) {
                this.logCustomerErrorMessage(error.message);
            } else if (error?.customerMessage) {
                this.logCustomerErrorMessage(error.customerMessage);
            } else if (error?.debug_id) {
                this.logCustomerErrorMessage(this.paymentMethodValidationError);
            } else {
                this.logCustomerErrorMessage(this.requestProcessingError);
            }
        },

        /** Restore quote values changed by an aborted SSSC attempt. */
        restoreQuoteAfterAbortedPayment: async function () {
            let response,
                result;

            if (!this.restoreQuoteUrl || this.location === 'PRODUCT_DETAIL') {
                return;
            }

            const body = new FormData();

            body.append('form_key', $.mage.cookies.get('form_key'));

            response = await fetch(this.restoreQuoteUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            });
            result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(this.requestProcessingError);
            }
        }
    });
});

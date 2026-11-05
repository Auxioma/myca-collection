/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2026 Adobe
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
    'mageUtils',
    'Magento_PaymentServicesPaypal/js/view/payment/paypal-abstract',
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/google-pay'
], function ($, _, utils, Component, $t, ResponseError, GooglePayButton) {
    'use strict';

    return Component.extend({
        defaults: {
            buttonContainerId: 'google-pay-${ $.uid }',
            template: 'Magento_PaymentServicesPaypal/payment/google-pay',
            placeOrderUrl: '',
            reviewPageUrl: '',
            shadowQuoteId: '',
            isVirtual: false,
            canSkipReviewForGooglePay: false,
            paymentSdkParams: {},
            paymentTypeIconTitle: $t('Pay with Google Pay'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            productFormSelector: '#product_addtocart_form'
        },

        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            config.uid = utils.uniqueid();
            this._super();
            this.element = element;
            this.element.id = this.buttonContainerId;
            this.initGooglePayButton();
            return this;
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super().observe('shadowQuoteId');
            this.shadowQuoteId.subscribe(this.beforeShadowQuoteIdChange, this, "beforeChange");
            return this;
        },

        beforeShadowQuoteIdChange: function (previousShadowQuoteId) {
            if (previousShadowQuoteId) {
                this.googlePayButton.paymentSdkReady
                    .then((sdk) => sdk._Utils.setCartAsInactive(previousShadowQuoteId))
                    .catch(() => console.warn(`Failed to set cart as inactive: '${previousShadowQuoteId}'.`));
            }
        },

        initGooglePayButton: function () {
            let createShadowQuotePromise = null;

            const getGooglePayRenderConfig = (defaultGooglePayRenderConfig) =>
                _.extend({}, defaultGooglePayRenderConfig, {
                    getCartId: async () => await createShadowQuotePromise,
                    getCartDetails: this.getCartDetails.bind(this),
                    onError: this.getOnErrorHandler(defaultGooglePayRenderConfig.onError),
                    onCancel: this.getOnCancelHandler(defaultGooglePayRenderConfig.onCancel),
                });

            this.googlePayButton = new GooglePayButton({
                location: "PRODUCT_DETAIL",
                placeOrderUrl: this.placeOrderUrl,
                reviewPageUrl: this.reviewPageUrl,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: this.paymentSdkParams,
                skipReviewPage: this.canSkipReviewForGooglePay,
                validate: this.validateProductForm.bind(this),
                getGooglePayRenderConfig: getGooglePayRenderConfig,
                onPaymentSheetOpen: () => {
                    createShadowQuotePromise = this.createShadowQuote();
                },
            });

            this.googlePayButton.renderGooglePayButton().catch(console.error);
        },

        /** Creates shadow cart with selected product and resolves to its (masked) cart id. */
        createShadowQuote: async function () {
            const body = new FormData($(this.productFormSelector)[0]);
            const response = await fetch(this.addToCartUrl, { method: "POST", body });
            const { success, error } = await response.json();
            if (success) {
                this.shadowQuoteId(success["quoteIdMask"]);
                return this.shadowQuoteId();
            } else {
                this.googlePayButton.refreshCustomerMessages();
                throw new ResponseError(error);
            }
        },

        /** Returns whether the product form is valid. */
        validateProductForm: function () {
            const $form = $(this.productFormSelector);
            return $form.data('mageValidation') && $form.validation('isValid');
        },

        /** Returns cart details in shape expected by the Google Pay SDK render config. */
        getCartDetails: function () {
            return {
                isVirtual: !!this.isVirtual,
            };
        },

        /** Extends default 'onError' handler to reset shadow quote id. */
        getOnErrorHandler: function (defaultHandler) {
            return (error) => {
                this.shadowQuoteId(null);
                defaultHandler(error);
            };
        },

        /** Extends default 'onCancel' handler to reset shadow quote id. */
        getOnCancelHandler: function (defaultHandler) {
            return () => {
                this.shadowQuoteId(null);
                defaultHandler();
            };
        },
    });
});

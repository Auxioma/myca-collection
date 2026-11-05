/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'jquery',
    'underscore',
    'mageUtils',
    'Magento_PaymentServicesPaypal/js/view/payment/paypal-abstract',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/apple-pay',
], function ($, _, utils, Component, $t, customerData, ResponseError, ApplePayButton) {
    'use strict';

    return Component.extend({
        defaults: {
            buttonContainerId: 'apple-pay-${ $.uid }',
            template: 'Magento_PaymentServicesPaypal/payment/apple-pay',
            placeOrderUrl: '',
            shadowQuoteId: '',
            paymentSdkParams: {},
            paymentTypeIconTitle: $t('Pay with Apple Pay'),
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
            this.initApplePayButton();
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
                this.applePayButton.paymentSdkReady
                    .then((sdk) => sdk._Utils.setCartAsInactive(previousShadowQuoteId))
                    .catch(() => console.warn(`Failed to set cart as inactive: '${previousShadowQuoteId}'.`));
            }
        },

        initApplePayButton: function () {
            let createShadowQuotePromise = null;

            const getApplePayRenderConfig = (defaultApplePayRenderConfig) =>
                _.extend({}, defaultApplePayRenderConfig, {
                    getCartId: async () => await createShadowQuotePromise,
                    onError: this.getOnErrorHandler(defaultApplePayRenderConfig.onError),
                    onCancel: this.getOnCancelHandler(defaultApplePayRenderConfig.onCancel),
                });

            this.applePayButton = new ApplePayButton({
                location: "PRODUCT_DETAIL",
                placeOrderUrl: this.placeOrderUrl,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: this.paymentSdkParams,
                validate: this.validateProductForm.bind(this),
                getApplePayRenderConfig: getApplePayRenderConfig,
                onPaymentSheetOpen: () => {
                    createShadowQuotePromise = this.createShadowQuote();
                },
            });

            this.applePayButton.renderApplePayButton().catch(console.error);
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
                this.applePayButton.refreshCustomerMessages();
                throw new ResponseError(error);
            }
        },

        /** Returns whether the product form is valid. */
        validateProductForm: function () {
            const $form = $(this.productFormSelector);
            return $form.data('mageValidation') && $form.validation('isValid');
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

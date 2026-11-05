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
    'underscore',
    'mageUtils',
    'Magento_PaymentServicesPaypal/js/view/payment/paypal-abstract',
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/smart-buttons',
    'priceBox'
], function ($, _, utils, Component, $t, ResponseError, SmartButtons) {
    'use strict';

    return Component.extend({
        defaults: {
            syncQuoteUrl: '',
            buttonContainerId: 'smart-buttons-${ $.uid }',
            canSkipReviewForPayPal: false,
            placeOrderUrl: '',
            shadowQuoteId: '',
            paymentSdkParams: {},
            paymentTypeIconTitle: $t('Pay with PayPal'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            productFormSelector: '#product_addtocart_form',
            baseCurrencyCode: '',
            currentCurrencyRate: null,
            priceBoxSelector: '.product-info-main [data-role="priceBox"], ' +
                '.product-add-form [data-role="priceBox"]'
        },

        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            config.uid = utils.uniqueid();
            this._super();
            this.element = element;
            this.element.id = this.buttonContainerId;
            this.initSmartButtons();
            return this;
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super().observe('shadowQuoteId');
            this.shadowQuoteId.subscribe(this.beforeShadowQuoteIdChange, this, 'beforeChange');
            return this;
        },

        beforeShadowQuoteIdChange: function (previousShadowQuoteId) {
            if (previousShadowQuoteId) {
                this.smartButtons.paymentSdkReady
                    .then((sdk) => sdk._Utils.setCartAsInactive(previousShadowQuoteId))
                    .catch(() => console.warn(`Failed to set cart as inactive: '${previousShadowQuoteId}'.`));
            }
        },

        initSmartButtons: function () {
            let createShadowQuotePromise = null;

            const getSmartButtonsRenderConfig = (defaultSmartButtonsRenderConfig) =>
                _.extend({}, defaultSmartButtonsRenderConfig, {
                    getCartId: async () => await createShadowQuotePromise,
                    onError: this.getOnErrorHandler(defaultSmartButtonsRenderConfig.onError),
                    onCancel: this.getOnCancelHandler(defaultSmartButtonsRenderConfig.onCancel)
                });

            this.smartButtons = new SmartButtons({
                location: 'PRODUCT_DETAIL',
                placeOrderUrl: this.placeOrderUrl,
                syncQuoteUrl: this.syncQuoteUrl,
                buttonContainerId: this.buttonContainerId,
                paymentSdkParams: this.paymentSdkParams,
                canSkipReviewForPayPal: this.canSkipReviewForPayPal,
                validate: this.validateProductForm.bind(this),
                getSmartButtonsRenderConfig: getSmartButtonsRenderConfig,
                onPaymentSheetOpen: function () {
                    createShadowQuotePromise = this.createShadowQuote();
                }.bind(this)
            });

            this.observePayLaterMessagePrice();
            this.smartButtons.renderSmartButtons().catch(console.error);
        },

        observePayLaterMessagePrice: function () {
            this.priceEventNamespace = '.payLaterMessage' + this.buttonContainerId;
            this.productPriceBox = $(this.priceBoxSelector);
            this.productPriceBox.on(
                'priceUpdated' + this.priceEventNamespace,
                this.onProductPriceUpdated.bind(this)
            );
            this.productPriceBox.trigger('updatePrice');
        },

        onProductPriceUpdated: function (event, displayPrices) {
            const basePrice = displayPrices && displayPrices.basePrice,
                finalPrice = displayPrices && displayPrices.finalPrice,
                finalPriceValue = finalPrice && finalPrice.amount !== null && finalPrice.amount !== undefined
                    ? Number(finalPrice.amount)
                    : null,
                basePriceValue = basePrice && basePrice.amount !== null && basePrice.amount !== undefined
                    ? Number(basePrice.amount)
                    : null,
                currencyRate = Number(this.currentCurrencyRate);
            let displayValue = finalPriceValue,
                value = null;

            if (!Number.isFinite(displayValue)) {
                displayValue = basePriceValue;
            }

            if (Number.isFinite(displayValue) && Number.isFinite(currencyRate) && currencyRate > 0) {
                value = Number((displayValue / currencyRate).toFixed(4));
            }

            this.smartButtons.updatePayLaterMessagePrice(Number.isFinite(value) ? {
                currency: this.baseCurrencyCode,
                value: value
            } : null);
        },

        /** @inheritdoc */
        destroy: function () {
            if (this.productPriceBox) {
                this.productPriceBox.off(this.priceEventNamespace);
                this.productPriceBox = null;
            }
            if (this.smartButtons) {
                this.smartButtons.disposePayLaterMessage();
            }

            return this._super();
        },

        /** Creates shadow cart with selected product and resolves to its (masked) cart id. */
        createShadowQuote: async function () {
            const body = new FormData($(this.productFormSelector)[0]),
                response = await fetch(this.addToCartUrl, { method: 'POST', body }),
                { success, error } = await response.json();

            if (success) {
                this.shadowQuoteId(success['quoteIdMask']);
                return this.shadowQuoteId();
            }

            this.smartButtons.refreshCustomerMessages();
            throw new ResponseError(error);
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
        }
    });
});

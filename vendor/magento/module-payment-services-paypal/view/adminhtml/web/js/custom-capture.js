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

define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/apply/main',
    'mage/translate',
    'prototype'
], function ($, ko, Component, mageApply, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Magento_PaymentServicesPaypal/order/invoice/create/custom-capture',
            captureCaseType: ko.observable(),
            containerSelector: '#payment-services-custom-capture',
            customCaptureAmount: '',
            customCaptureReason: '',
            amountError: '',
            targetId: '#invoice_item_container',
            updateTotalsUrl: ''
        },

        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            this._super();

            this.element = element || document.querySelector(this.containerSelector);
            this.container = $(this.element || this.containerSelector);

            const $captureCaseType = $('[name="invoice[capture_case]"]');
            this.captureCaseType($captureCaseType.val());
            $captureCaseType.on('change', (event) => {
               this.captureCaseType($(event.currentTarget).val());
            });
            this.customCaptureAmount.subscribe(this.validateAmount.bind(this));
            this.validateAmount();

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super()
                .observe([
                    'customCaptureAmount',
                    'customCaptureReason',
                    'amountError'
                ]);

            return this;
        },

        /**
         * Get totals reload URL.
         *
         * @returns {String}
         */
        getReloadUrl: function () {
            return this.updateTotalsUrl + (this.updateTotalsUrl.match(/\?/) ? '&isAjax=true' : '?isAjax=true');
        },

        /**
         * Reload invoice totals.
         */
        reloadTotals: function () {
            var target = $(this.targetId),
                fields,
                data,
                invoiceOptions,
                loader;

            if (!target.length || !this.validateAmount()) {
                return;
            }

            loader = $(target);
            fields = target.find('input, select, textarea');
            data = this.appendFormKey($(fields).serialize());
            invoiceOptions = this.getInvoiceOptions(target);

            loader.trigger('processStart');

            fetch(this.getReloadUrl(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: data
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to reload invoice totals.');
                    }

                    return response.text();
                })
                .then(function (responseText) {
                    this.processReloadResponse(target, responseText, invoiceOptions);
                }.bind(this))
                .catch(function () {
                    alert($t('Cannot update custom capture totals.'));
                })
                .finally(function () {
                    loader.trigger('processStop');
                });
        },

        /**
         * Append admin form key when it is not already present.
         *
         * @param {String} data
         * @returns {String}
         */
        appendFormKey: function (data) {
            if (data.indexOf('form_key=') !== -1 || !window.FORM_KEY) {
                return data;
            }

            return data + (data ? '&' : '') + $.param({
                form_key: window.FORM_KEY
            });
        },

        /**
         * Process reload response.
         *
         * @param {jQuery} target
         * @param {String} responseText
         * @param {Object} invoiceOptions
         */
        processReloadResponse: function (target, responseText, invoiceOptions) {
            try {
                var response = JSON.parse(responseText);

                if (response.error) {
                    alert(response.message);
                }

                if (response.ajaxExpired && response.ajaxRedirect) {
                    window.setLocation(response.ajaxRedirect);
                }
            } catch (e) {
                target.html(responseText);
                this.restoreInvoiceOptions(target, invoiceOptions);
                mageApply.apply(target.get(0));
                this.applyBindings(target);
                this.triggerInvoiceOptionChange(target);
            }
        },

        /**
         * Get invoice options replaced with the invoice items container.
         *
         * @param {jQuery} target
         * @returns {Object}
         */
        getInvoiceOptions: function (target) {
            var options = {};

            target.find('[name="invoice[send_email]"], [name="invoice[comment_customer_notify]"], [name="invoice[capture_case]"]')
                .each(function (index, element) {
                    var field = $(element),
                        name = field.attr('name');

                    options[name] = {
                        checked: field.prop('checked'),
                        value: field.val()
                    };
                });

            return options;
        },

        /**
         * Restore invoice options after refreshing the invoice items container.
         *
         * @param {jQuery} target
         * @param {Object} invoiceOptions
         * @returns {void}
         */
        restoreInvoiceOptions: function (target, invoiceOptions) {
            target.find('[name="invoice[send_email]"], [name="invoice[comment_customer_notify]"], [name="invoice[capture_case]"]')
                .each(function (index, element) {
                    var field = $(element),
                        option = invoiceOptions[field.attr('name')];

                    if (!option) {
                        return;
                    }

                    if (field.is(':checkbox, :radio')) {
                        field.prop('checked', option.checked);
                    } else {
                        field.val(option.value);
                    }
                });
        },

        /**
         * Trigger Magento's normal handling for restored invoice options.
         *
         * @param {jQuery} target
         * @returns {void}
         */
        triggerInvoiceOptionChange: function (target) {
            target.find('[name="invoice[send_email]"], [name="invoice[comment_customer_notify]"], [name="invoice[capture_case]"]')
                .trigger('change');
        },

        /**
         * Apply Knockout bindings to refreshed component markup.
         *
         * @param {jQuery} target
         */
        applyBindings: function (target) {
            target.find(this.containerSelector).each(function (index, element) {
                ko.applyBindings({}, element);
            });
        },

        /**
         * Has amount.
         *
         * @returns {boolean}
         */
        hasAmount: function () {
            return $.trim(this.customCaptureAmount()) !== '';
        },

        /**
         * Validate the client-side minimum amount.
         *
         * @returns {boolean}
         */
        validateAmount: function () {
            var amount = $.trim(this.customCaptureAmount()),
                numericAmount;

            if (amount === '') {
                this.amountError('');

                return true;
            }

            numericAmount = Number(amount);
            if (!Number.isFinite(numericAmount)) {
                this.amountError($t('Additional Amount must be a valid number.'));

                return false;
            }

            if (numericAmount < 0) {
                this.amountError($t('Additional Amount must not be negative.'));

                return false;
            }

            if (numericAmount < 0.01) {
                this.amountError($t('Additional Amount must be at least 0.01.'));

                return false;
            }

            this.amountError('');

            return true;
        }
    });
});

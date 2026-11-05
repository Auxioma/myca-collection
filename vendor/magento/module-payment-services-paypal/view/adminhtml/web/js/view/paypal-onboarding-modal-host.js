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
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Ui/js/modal/modal',
    'Magento_PaymentServicesPaypal/js/model/paypal-onboarding-modal-actions',
    'Magento_PaymentServicesPaypal/js/model/paypal-onboarding-message-list',
    'loader',
], function (Component, ko, $, modal, modalActions, messageList) {
    'use strict';

    return Component.extend({
        modalComponent: ko.observable(false),
        messageContainer: messageList,

        initialize: function () {
            var self = this,
                modalOptions = {
                    type: 'popup',
                    modalClass: 'paypal-onboarding-modal',
                    responsive: true,
                    innerScroll: true,
                    buttons: [],
                    closed: function () {
                        self.notifyModalClosed();
                        messageList.clear();
                        self.resetModalState();
                    }
                };

            this._super();
            this.modalCloseHandlers = [];
            this.$modalElement = $(this.modalSelector);
            this.$modalElement.loader();
            modal(modalOptions, this.$modalElement);

            if (this.triggerSelector) {
                $(this.triggerSelector).on('click', function () {
                    modalActions.openModal(self.defaultModal || {});
                });
            }

            document.addEventListener('openPaypalOnboardingModal', function (event) {
                self.openModal(event.detail || {});
            });
            document.addEventListener('closePaypalOnboardingModal', function () {
                self.closeModal();
            });

            return this;
        },

        openModal: function (options) {
            if (!options.name || !options.component || !options.template) {
                return;
            }

            if (!ko.components.isRegistered(options.name)) {
                ko.components.register(options.name, {
                    viewModel: {require: options.component},
                    template: {require: 'text!' + options.template},
                    synchronous: true
                });
            }

            this.modalComponent({
                name: options.name,
                params: this.getModalComponentParams(options.data || {})
            });
            this.$modalElement.modal('openModal');
        },

        getModalComponentParams: function (data) {
            var params = data || {};

            return Object.assign({}, params, {
                modalHost: {
                    messageContainer: this.messageContainer,
                    onClose: this.onClose.bind(this),
                    closeModal: this.closeModal.bind(this),
                    showLoader: this.showLoader.bind(this),
                    hideLoader: this.hideLoader.bind(this),
                    getLoaderElement: this.getLoaderElement.bind(this)
                }
            });
        },

        onClose: function (handler) {
            if (typeof handler !== 'function') {
                return function () {};
            }

            this.modalCloseHandlers.push(handler);

            return function () {
                this.modalCloseHandlers = this.modalCloseHandlers.filter(function (modalCloseHandler) {
                    return modalCloseHandler !== handler;
                });
            }.bind(this);
        },

        notifyModalClosed: function () {
            this.modalCloseHandlers.slice().forEach(function (handler) {
                handler();
            });
        },

        closeModal: function () {
            this.$modalElement.modal('closeModal');
        },

        resetModalState: function () {
            this.modalComponent(false);
        },

        showLoader: function () {
            this.$modalElement.loader('show');
        },

        hideLoader: function () {
            this.$modalElement.loader('hide');
        },

        getLoaderElement: function () {
            return this.$modalElement.find('.loading-mask').get(0) || null;
        }
    });
});

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
    'Magento_PaymentServicesPaypal/js/model/paypal-onboarding-modal-actions'
], function (Component, modalActions) {
    'use strict';

    return Component.extend({
        /**
         * Initialize the disconnect global account modal component.
         *
         * @param {Object} params
         * @returns {Object}
         */
        initialize: function (params) {
            this._super();
            this.modalHost = (params && params.modalHost) || null;
            this.loadCountriesUrl = (params && params.loadCountriesUrl) || '';
            this.scopeOnboardingUrl = (params && params.scopeOnboardingUrl) || '';
            this.onboardingStatusUrl = (params && params.onboardingStatusUrl) || '';

            return this;
        },

        /**
         * Continue to payment account onboarding.
         *
         * @returns {void}
         */
        confirmAndConnectNewAccount: function () {
            modalActions.openModal({
                name: 'paypal-onboarding-payment-exp',
                component: 'Magento_PaymentServicesPaypal/js/view/modals/paypal-onboarding/payment-experience',
                template: 'Magento_PaymentServicesPaypal/template/modals/paypal-onboarding/payment-experience.html',
                data: {
                    loadCountriesUrl: this.loadCountriesUrl,
                    scopeOnboardingUrl: this.scopeOnboardingUrl,
                    onboardingStatusUrl: this.onboardingStatusUrl
                }
            });
        },

        /**
         * Close the onboarding modal.
         *
         * @returns {void}
         */
        cancel: function () {
            if (this.modalHost && typeof this.modalHost.closeModal === 'function') {
                this.modalHost.closeModal();
            }
        }
    });
});

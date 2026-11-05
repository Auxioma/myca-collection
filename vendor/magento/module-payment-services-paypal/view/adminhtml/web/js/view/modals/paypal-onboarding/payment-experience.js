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
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/model/paypal-onboarding-modal-actions'
], function (Component, ko, $t, modalActions) {
    'use strict';

    const paymentExperienceTypes = {
        standard: 'EXPRESS_CHECKOUT',
        advanced: 'PPCP'
    };

    return Component.extend({
        /**
         * Initialize the payment experience modal component.
         *
         * @param {Object} params
         * @returns {Object}
         */
        initialize: function (params) {
            this._super();
            this.message = (params && params.message) || this.message;
            this.modalHost = (params && params.modalHost) || null;
            this.loadCountriesUrl = (params && params.loadCountriesUrl) || '';
            this.scopeOnboardingUrl = (params && params.scopeOnboardingUrl) || '';
            this.onboardingStatusUrl = (params && params.onboardingStatusUrl) || '';
            this.countries = ko.observableArray([]);
            this.selectedCountry = ko.observable((params && params.selectedCountry) || null);
            this.selectCountryOptionsCaption = ko.observable($t('Loading...'));
            this.selectedPaymentExperience = ko.observable(null);
            this.partnerReferralUrl = ko.observable('');
            this.hasOpenedPaymentServicesAgreement = ko.observable(false);
            this.closePayPalOnboarding = null;
            this.removeModalCloseHandler = null;

            if (this.modalHost && typeof this.modalHost.onClose === 'function') {
                this.removeModalCloseHandler = this.modalHost.onClose(this.handleModalClosed.bind(this));
            }

            this.setDefaultPaymentExperience(this.selectedCountry());

            this.selectedCountry.subscribe((country) => {
                this.setDefaultPaymentExperience(country);
            });

            this.loadCountries();

            return this;
        },

        /**
         * Dispose active requests and popup listeners when the modal component is removed.
         *
         * @returns {void}
         */
        dispose: function () {
            if (this.removeModalCloseHandler) {
                this.removeModalCloseHandler();
                this.removeModalCloseHandler = null;
            }

            this.handleModalClosed();
        },

        /**
         * Handle the host modal being closed.
         *
         * @returns {void}
         */
        handleModalClosed: function () {
            if (this.closePayPalOnboarding) {
                this.closePayPalOnboarding();
            }
        },

        /**
         * Select the default payment experience for a country.
         *
         * @param {Object|null} country
         * @returns {void}
         */
        setDefaultPaymentExperience: function (country) {
            if (!country) {
                this.selectedPaymentExperience(null);
                return;
            }

            if (country.ppcp) {
                this.selectedPaymentExperience('advanced');
                return;
            }

            this.selectedPaymentExperience('standard');
        },

        /**
         * Update the selected payment experience and refresh the partner referral URL.
         *
         * @param {String} experience
         * @returns {void}
         */
        selectPaymentExperience: function (experience) {
            if (this.selectedPaymentExperience() === experience) {
                return;
            }

            this.selectedPaymentExperience(experience);
        },

        /**
         * Check whether the payment experience is currently selected.
         *
         * @param {String} experience
         * @returns {Boolean}
         */
        isPaymentExperienceSelected: function (experience) {
            return this.selectedPaymentExperience() === experience;
        },

        /**
         * Check whether the selected country supports PPCP onboarding.
         *
         * @returns {Boolean}
         */
        isSelectedCountryPpcp: function () {
            let selectedCountry = this.selectedCountry();

            return !!(selectedCountry && selectedCountry.ppcp);
        },

        /**
         * Track that the Payment Services agreement has been opened.
         *
         * @returns {Boolean}
         */
        handlePaymentServicesAgreementClick: function () {
            this.hasOpenedPaymentServicesAgreement(true);

            return true;
        },

        /**
         * Load onboarding country options from the Payment Services onboarding status endpoint.
         *
         * @returns {void}
         */
        loadCountries: function () {
            if (!this.loadCountriesUrl) {
                this.countries([]);
                console.error('[PaymentServicesPaypal] Missing loadCountriesUrl for onboarding countries request.');
                return;
            }

            fetch(this.loadCountriesUrl, {
                headers: {
                    accept: 'application/json'
                },
                method: 'GET',
                credentials: 'include'
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Failed to load onboarding status. HTTP ' + response.status);
                    }

                    return response.json();
                })
                .then((payload) => {
                    let countries = [],
                        result = payload && payload.result;

                    if (!result || result.status !== 'OK') {
                        throw new Error('Invalid onboarding status response.');
                    }

                    if (Array.isArray(result.countries)) {
                        countries = result.countries;
                    }

                    this.countries(countries);
                    this.selectCountryOptionsCaption($t('Select Country'));
                })
                .catch((error) => {
                    this.countries([]);
                    console.error('[PaymentServicesPaypal] Unable to load onboarding countries.', error);
                    this.modalHost.messageContainer.addErrorMessage($t('There has been an error getting available countries. Please refresh and try again.'));
                });
        },

        /**
         * Request the PayPal partner referral URL for the selected country and payment experience.
         *
         * @returns {Promise<void>}
         */
        scopeOnboardingRequest: async function () {
            let selectedCountry = this.selectedCountry(),
                selectedPaymentExperience = this.selectedPaymentExperience(),
                onboardingType = paymentExperienceTypes[selectedPaymentExperience],
                response,
                payload,
                redirect;

            this.partnerReferralUrl('');

            if (!selectedCountry || !selectedCountry.code || !onboardingType) {
                return;
            }

            if (!this.scopeOnboardingUrl) {
                this.modalHost.messageContainer.clear();
                this.modalHost.messageContainer.addErrorMessage($t('Unable to start PayPal onboarding. Please try again later.'));
                console.error('Missing scopeOnboardingUrl for scope onboarding request.');
                return;
            }

            try {
                response = await fetch(this.buildScopeOnboardingUrl(selectedCountry.code, onboardingType), {
                    headers: {
                        accept: 'application/json'
                    },
                    method: 'GET',
                    credentials: 'include'
                });

                if (!response.ok) {
                    throw new Error('Failed to start scope onboarding. HTTP ' + response.status);
                }

                payload = await response.json();
                redirect = payload && payload.redirect;

                if (!redirect || !redirect.url) {
                    throw new Error('Invalid scope onboarding response.');
                }

                this.partnerReferralUrl(redirect.url);
            } catch (error) {
                this.modalHost.messageContainer.clear();
                this.modalHost.messageContainer.addErrorMessage($t('Unable to start PayPal onboarding. Please try again later.'));
                console.error('Unable to prepare scope onboarding URL.', error);
            }
        },

        /**
         * Build the scope onboarding admin URL with selected country and onboarding type.
         *
         * @param {String} countryCode
         * @param {String} onboardingType
         * @returns {String}
         */
        buildScopeOnboardingUrl: function (countryCode, onboardingType) {
            let url = new URL(this.scopeOnboardingUrl, window.location.origin);

            url.searchParams.set('country', countryCode);
            url.searchParams.set('type', onboardingType);
            url.searchParams.set('isAjax', 'true');

            return url.toString();
        },

        /**
         * Check whether the modal can continue to PayPal onboarding.
         *
         * @returns {Boolean}
         */
        isContinueEnabled: function () {
            return this.isSelectedCountryPpcp() || this.hasOpenedPaymentServicesAgreement();
        },

        /**
         * Get the continue button label for the selected country.
         *
         * @returns {String}
         */
        getContinueButtonLabel: function () {
            return this.isSelectedCountryPpcp() ? $t('Confirm & Continue') : $t('I accept');
        },

        /**
         * Handle the PayPal onboarding popup being closed.
         *
         * @returns {void}
         */
        handlePayPalOnboardingClosed: async function () {
            this.modalHost.messageContainer.clear();

            if (!this.onboardingStatusUrl) {
                this.modalHost.messageContainer.addErrorMessage($t('Unable to check PayPal onboarding status.'));
                console.error('[PaymentServicesPaypal] Missing onboardingStatusUrl for onboarding status request.');
                this.hideModalLoader();
                return;
            }

            let retry = 0;

            try {
                while (retry < 3) {
                    let { status } = await this.getOnboardingStatus();

                    if (status === 'UNVERIFIED' || status === 'REVOKED') {
                        this.modalHost.messageContainer.addErrorMessage($t('There has been an error authenticating your PayPal account. Please wait while we refresh the page.'));
                        setTimeout(() => window.location.reload(), 5000);
                        break;
                    } else if (status === 'COMPLETED') {
                        modalActions.openModal({
                            name: 'paypal-onboarding-success',
                            component: 'Magento_PaymentServicesPaypal/js/view/modals/paypal-onboarding/success',
                            template: 'Magento_PaymentServicesPaypal/template/modals/paypal-onboarding/success.html'
                        });
                        break;
                    } else if (status === 'STARTED') {
                        if (retry === 2) {
                            this.modalHost.messageContainer.addErrorMessage($t('Onboarding was not completed successfully. Please try again.'));
                        } else {
                            await new Promise((resolve) => setTimeout(() => resolve(), 5000));
                        }

                        retry += 1;
                    }
                }

            } catch (error) {
                this.modalHost.messageContainer.addErrorMessage($t('Unable to check PayPal onboarding status.'));
                console.error('[PaymentServicesPaypal] Unable to load onboarding status.', error);
            }

            this.hideModalLoader();
        },

        /**
         * Load the current website-scope PayPal onboarding status.
         *
         * @returns {Promise<Object>}
         */
        getOnboardingStatus: async function () {
            let response = await fetch(this.onboardingStatusUrl, {
                headers: {
                    accept: 'application/json'
                },
                method: 'GET',
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Failed to load onboarding status. HTTP ' + response.status);
            }

            return response.json();
        },

        /**
         * Show the host modal loader when the current host supports it.
         *
         * @returns {void}
         */
        showModalLoader: function () {
            if (this.modalHost && typeof this.modalHost.showLoader === 'function') {
                this.modalHost.showLoader();
            }
        },

        /**
         * Hide the host modal loader when the current host supports it.
         *
         * @returns {void}
         */
        hideModalLoader: function () {
            if (this.modalHost && typeof this.modalHost.hideLoader === 'function') {
                this.modalHost.hideLoader();
            }
        },

        /**
         * Get the host modal loader element when the current host supports it.
         *
         * @returns {HTMLElement|null}
         */
        getModalLoaderElement: function () {
            if (this.modalHost && typeof this.modalHost.getLoaderElement === 'function') {
                return this.modalHost.getLoaderElement();
            }

            return null;
        },

        /**
         * Build popup features for the PayPal onboarding window.
         *
         * @returns {String}
         */
        getPayPalOnboardingPopupFeatures: function () {
            const popupWidth = 750,
                popupHeight = 1000,
                popupLeft = window.screenX + ((window.outerWidth - popupWidth) / 2),
                popupTop = window.screenY + ((window.outerHeight - popupHeight) / 2);

            return [
                'popup=yes',
                'width=' + popupWidth,
                'height=' + popupHeight,
                'left=' + Math.max(0, popupLeft),
                'top=' + Math.max(0, popupTop),
                'resizable=yes',
                'scrollbars=yes'
            ].join(',');
        },

        /**
         * Open the PayPal partner referral URL in a centered popup.
         *
         * @param {String} partnerReferralUrl
         * @returns {Window|null}
         */
        openPayPalOnboardingPopup: function (partnerReferralUrl) {
            return window.open(
                partnerReferralUrl + '&displayMode=minibrowser',
                'payPalOnboarding',
                this.getPayPalOnboardingPopupFeatures()
            );
        },

        /**
         * Track the PayPal onboarding popup until it closes.
         *
         * @param {Window} payPalOnboarding
         * @returns {void}
         */
        trackPayPalOnboardingPopup: function (payPalOnboarding) {
            let popupClosedTimer = null,
                removeLoadingElementFocusListener = null,
                closePayPalOnboarding = null;

            const cleanupPayPalOnboarding = () => {
                if (popupClosedTimer) {
                    clearInterval(popupClosedTimer);
                    popupClosedTimer = null;
                }

                window.removeEventListener('beforeunload', closePayPalOnboarding);
                window.removeEventListener('pagehide', closePayPalOnboarding);

                if (removeLoadingElementFocusListener) {
                    removeLoadingElementFocusListener();
                    removeLoadingElementFocusListener = null;
                }

                if (this.closePayPalOnboarding === closePayPalOnboarding) {
                    this.closePayPalOnboarding = null;
                }
            };

            closePayPalOnboarding = () => {
                if (!payPalOnboarding.closed) {
                    payPalOnboarding.close();
                }

                cleanupPayPalOnboarding();
            };

            const loadingElement = this.getModalLoaderElement();

            window.addEventListener('beforeunload', closePayPalOnboarding);
            window.addEventListener('pagehide', closePayPalOnboarding);
            this.closePayPalOnboarding = closePayPalOnboarding;

            if (loadingElement) {
                const focusPayPalOnboarding = () => {
                    if (!payPalOnboarding.closed) {
                        payPalOnboarding.focus();
                    }
                };

                loadingElement.addEventListener('click', focusPayPalOnboarding);
                removeLoadingElementFocusListener = () => {
                    loadingElement.removeEventListener('click', focusPayPalOnboarding);
                };
            }

            popupClosedTimer = setInterval(async () => {
                if (payPalOnboarding.closed) {
                    cleanupPayPalOnboarding();
                    await this.handlePayPalOnboardingClosed();
                }
            }, 2000);

            payPalOnboarding.focus();
        },

        /**
         * Start PayPal onboarding.
         *
         * @returns {Promise<void>}
         */
        startPayPalOnboarding: async function () {
            let partnerReferralUrl,
                payPalOnboarding;

            this.showModalLoader();
            await this.scopeOnboardingRequest();

            partnerReferralUrl = this.partnerReferralUrl();

            if (!partnerReferralUrl) {
                this.hideModalLoader();
                return;
            }

            if (this.closePayPalOnboarding) {
                this.closePayPalOnboarding();
            }

            payPalOnboarding = this.openPayPalOnboardingPopup(partnerReferralUrl);

            if (!payPalOnboarding) {
                this.hideModalLoader();
                return;
            }

            this.trackPayPalOnboardingPopup(payPalOnboarding);
        }
    });
});

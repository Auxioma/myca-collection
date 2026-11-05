/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
define(function () {
    'use strict';

    let mixin = {
        /**
         * Override the init CAPTCHA to reset the initialise state for Braintree on each initialisation.
         */
        initCaptcha: function () {
            if (this.reCaptchaId === 'recaptcha-checkout-braintree') {
                this.captchaInitialized = false;
            }

            return this._super();
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});

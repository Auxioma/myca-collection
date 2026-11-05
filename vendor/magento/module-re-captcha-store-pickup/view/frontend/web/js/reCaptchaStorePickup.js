/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */

define(['Magento_ReCaptchaFrontendUi/js/reCaptcha'], function (reCaptcha) {
    'use strict'; // eslint-disable-line

    return reCaptcha.extend({

        /**
         * @inheritdoc
         */
        renderReCaptcha: function () {
            this.captchaInitialized = false;
            this._super();
        }
    });
});

/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

define(['Magento_ReCaptchaFrontendUi/js/registry'], function (registry) {
    'use strict'; // eslint-disable-line

    return function (originalComponent) {
        return originalComponent.extend({
            /**
             * Initialize reset on messages
             * @returns {initialize}
             */
            initialize: function () {
                this._super();

                this.messageContainer.errorMessages.subscribe(function () {
                    var
                        i,
                        captchaList = registry.captchaList(),
                        tokenFieldsList = registry.tokenFields();

                    for (i = 0; i < captchaList.length; i++) {
                        // eslint-disable-next-line no-undef
                        grecaptcha.reset(captchaList[i]);

                        if (tokenFieldsList[i]) {
                            tokenFieldsList[i].value = '';
                        }
                    }
                }, null, 'arrayChange');

                return this;
            }
        });
    };
});

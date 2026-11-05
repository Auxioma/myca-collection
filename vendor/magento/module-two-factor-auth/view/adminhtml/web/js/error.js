/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert) {
    'use strict';

    return {
        /**
         * Display an error message
         * @param {String} message
         */
        display: function (message) {
            alert({
                title: $.mage.__('Error'),
                content: message
            });
        }
    };
});

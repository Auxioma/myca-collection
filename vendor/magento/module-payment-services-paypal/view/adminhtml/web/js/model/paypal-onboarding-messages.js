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
    'ko',
    'uiClass'
], function (ko, Class) {
    'use strict';

    return Class.extend({
        /**
         * Initialize message storage.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super()
                .initObservable();

            return this;
        },

        /**
         * Initialize observable message arrays.
         *
         * @returns {Object}
         */
        initObservable: function () {
            this.errorMessages = ko.observableArray([]);
            this.successMessages = ko.observableArray([]);

            return this;
        },

        /**
         * Add message to a message list.
         *
         * @param {String} message
         * @param {Function} type
         * @returns {Boolean}
         */
        add: function (message, type) {
            type.push(message);

            return true;
        },

        /**
         * Add success message.
         *
         * @param {Object|String} message
         * @returns {Boolean}
         */
        addSuccessMessage: function (message) {
            return this.add(message, this.successMessages);
        },

        /**
         * Add error message.
         *
         * @param {Object|String} message
         * @returns {Boolean}
         */
        addErrorMessage: function (message) {
            return this.add(message, this.errorMessages);
        },

        /**
         * Get error messages.
         *
         * @returns {Function}
         */
        getErrorMessages: function () {
            return this.errorMessages;
        },

        /**
         * Get success messages.
         *
         * @returns {Function}
         */
        getSuccessMessages: function () {
            return this.successMessages;
        },

        /**
         * Check whether there are messages to show.
         *
         * @returns {Boolean}
         */
        hasMessages: function () {
            return this.errorMessages().length > 0 || this.successMessages().length > 0;
        },

        /**
         * Remove a message from a message list.
         *
         * @param {String} type
         * @param {String} message
         * @returns {void}
         */
        remove: function (type, message) {
            if (type === 'success') {
                this.successMessages.remove(message);
                return;
            }

            if (type === 'error') {
                this.errorMessages.remove(message);
            }
        },

        /**
         * Remove all stored messages.
         *
         * @returns {void}
         */
        clear: function () {
            this.errorMessages.removeAll();
            this.successMessages.removeAll();
        }
    });
});

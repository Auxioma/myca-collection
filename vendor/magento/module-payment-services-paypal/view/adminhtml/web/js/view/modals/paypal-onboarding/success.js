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
    'uiComponent'
], function (Component) {
    'use strict';

    return Component.extend({
        /**
         * Initialize the success modal component.
         *
         * @param {Object} params
         * @returns {Object}
         */
        initialize: function (params) {
            this._super();
            this.modalHost = (params && params.modalHost) || null;
            this.removeModalCloseHandler = null;

            if (this.modalHost && typeof this.modalHost.onClose === 'function') {
                this.removeModalCloseHandler = this.modalHost.onClose(this.handleModalClosed.bind(this));
            }

            return this;
        },

        /**
         * Remove registered modal close handlers.
         *
         * @returns {void}
         */
        dispose: function () {
            if (this.removeModalCloseHandler) {
                this.removeModalCloseHandler();
                this.removeModalCloseHandler = null;
            }
        },

        /**
         * Reload the current page when the host modal closes.
         *
         * @returns {void}
         */
        handleModalClosed: function () {
            window.location.reload();
        }
    });
});

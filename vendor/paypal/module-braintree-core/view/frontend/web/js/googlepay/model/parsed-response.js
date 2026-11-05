/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
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
    'underscore',
    'ko'
], function (_, ko) {
    'use strict';

    let nonce = ko.observable(null),
        isNetworkTokenized = ko.observable(false),
        bin = ko.observable(null);

    return {
        nonce: nonce,
        isNetworkTokenized: isNetworkTokenized,
        bin: bin,

        /**
         * Get the payment nonce.
         *
         * @return {?string}
         */
        getNonce: function () {
            return this.nonce();
        },

        /**
         * Set the payment nonce.
         *
         * @param {?string} value
         * @return {void}
         */
        setNonce: function (value) {
            this.nonce(_.isString(value) ? value : null);
        },

        /**
         * Get is network tokenized property for used card.
         *
         * @return {boolean}
         */
        getIsNetworkTokenized: function () {
            return this.isNetworkTokenized();
        },

        /**
         * Set is network tokenized property for used card.
         *
         * @param {boolean} value
         * @return {void}
         */
        setIsNetworkTokenized: function (value) {
            this.isNetworkTokenized(_.isBoolean(value) ? value : false);
        },

        /**
         * Get card bin.
         *
         * @return {?string}
         */
        getBin: function () {
            return this.bin();
        },

        /**
         * Set the card bin.
         *
         * @param {?string} value
         * @return {void}
         */
        setBin: function (value) {
            this.bin(_.isString(value) ? value : null);
        },

        /**
         * Reset data to default.
         */
        resetDefaultData: function () {
            this.setNonce(null);
            this.setIsNetworkTokenized(false);
            this.setBin(null);
        }
    };
});

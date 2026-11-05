/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

define(['ko'], function (ko) {
    'use strict'; // eslint-disable-line

    return {
        ids: ko.observableArray([]),
        captchaList: ko.observableArray([]),
        tokenFields: ko.observableArray([])
    };
});

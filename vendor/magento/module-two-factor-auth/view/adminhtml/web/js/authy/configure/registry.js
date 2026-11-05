/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

define([
    'ko'
], function (ko) {
    'use strict';

    return {
        currentStep: ko.observable('register'),
        messageText: ko.observable(''),
        secondsToExpire: ko.observable(0)
    };
});

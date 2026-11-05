/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

define([
    'ko',
    'uiComponent',
    'Magento_TwoFactorAuth/js/authy/configure/registry'
], function (ko, Component, registry) {
    'use strict';

    return Component.extend({
        currentStep: registry.currentStep,
        defaults: {
            template: 'Magento_TwoFactorAuth/authy/configure'
        }
    });
});

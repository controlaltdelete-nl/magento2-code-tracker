/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

define(function () {
    'use strict';

    const appSwitchKey = 'paypal-app-switch';

    return {
        clearData: () => {
            window.localStorage.removeItem(appSwitchKey);
        },

        getData: (key) => {
            return JSON.parse(window.localStorage.getItem(appSwitchKey) || '{}')[key];
        },

        setData: (key, value) => {
            const data = JSON.parse(window.localStorage.getItem(appSwitchKey) || '{}');

            data[key] = value;

            window.localStorage.setItem(appSwitchKey, JSON.stringify(data));
        }
    };
});

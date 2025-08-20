/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'uiComponent',
    'scriptLoader'
], function ($, Component, loadSdkScript) {
    'use strict';

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypal',
            renderContainer: null,
            amountAttribute: 'data-pp-amount',
            amount: null
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            this.sdkLoaded = loadSdkScript(this.scriptParams, this.sdkNamespace);

            return this;
        },

        /**
         * Update amount
         *
         * @param {*} amount
         */
        updateAmount: function (amount) {
            this.amount = amount;
            $(this.renderContainer).attr(this.amountAttribute, this.amount);
        },

        /**
         * Render message
         *
         * @return {Promise}
         */
        render: function () {
            return this.sdkLoaded.then(function (sdkScript) {
                const styles = this.getStyles();

                if (!styles) {
                    return;
                }

                sdkScript.Messages({
                    amount: parseFloat(this.amount).toFixed(2),
                    placement: this.placement,
                    style: styles
                })
                .render(this.renderContainer);
            }.bind(this)).catch(function (exception) {
                console.log('Error: Failed to load PayPal SDK script!');
                console.log(exception.message);
            });
        },

        /**
         * Gets the Pay Later Message styling for the given placement.
         *
         * Returns null if the placement is disabled.
         *
         * @returns {Object|bool}
         */
        getStyles: function () {
            if (!this.styles) {
                return {};
            }

            let parsedStyles = JSON.parse(this.styles),
                placement = this.placement === 'payment' ? 'checkout' : this.placement,
                styles = parsedStyles[placement];

            if (!styles) {
                return false;
            }

            return styles;
        }
    });
});

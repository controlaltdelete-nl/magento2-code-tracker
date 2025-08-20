/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'jquery',
    'underscore',
    'uiComponent',
    'scriptLoader',
    'Magento_Customer/js/customer-data'
], function ($, _, Component, loadSdkScript, customerData) {
    'use strict';

    /**
     * Create order request.
     *
     * @param {String} url
     * @param {Object} payPalOrderData
     * @param {FormData} orderData
     * @return {Promise<Object>}
     */
    const performCreateOrder = function (url, payPalOrderData, orderData) {
        orderData = orderData || new FormData();
        orderData.append('form_key', $.mage.cookies.get('form_key'));
        orderData.append('payment_source', payPalOrderData['paymentSource']);

        return fetch(url, {
            method: 'POST',
            headers: {},
            body: orderData || new FormData(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    },

    /**
     * Payment authorization request.
     *
     * @return {Promise<Object>}
     */
    performAuthorization = function (url, data) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                orderId: data.orderID
            })
        }).then((response) => {
            if (!response.ok) {
                throw new Error($t('We’re unable to take that order right now.'));
            }
            return response.json();
        }).then((response)  => {
            const parsed = JSON.parse(response);
            if (!parsed.success) {
                throw new Error($t('We’re unable to take that order right now.'));
            }

            customerData.invalidate(['cart']);
            window.location.replace(parsed.redirectUrl);
        });
    };

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypal',
            paypal: null,
            paymentSource: '',
            creatOrderUrl: '',
            placeOrderUrl: '',
            authorizeOrderUrl: '',
            setQuoteAsInactiveUrl: '',
            completeOrderUrl: '',
            style: {},
            paymentRequest: {
                applepay: {
                    requiredShippingContactFields: []
                }
            },
            element: null,
            instance: null
        },

        /** @inheritdoc */
        initialize: function () {
            _.bindAll(this, 'createOrder', 'onApprove', 'onError', 'onCancel');
            this._super();
            this.sdkLoaded = loadSdkScript(this.scriptParams, this.sdkNamespace).then(function (sdkScript) {
                this.paypal = sdkScript;
            }.bind(this));

            return this;
        },

        /**
         * In the case where the button color is not supported by Apple (black or white)
         * Map the button color to black (same behavior as PayPal SDK script)
         *
         * @param buttonStyles
         * @returns {(*&{color: string})|*}
         */
        mapButtonColorForApplePay: function (buttonStyles) {
            var buttonColor = buttonStyles.color;

            if (buttonColor === 'black' || buttonColor === 'white') {
                return buttonStyles;
            }
            return {
                ...buttonStyles,
                color: 'black'
            };
        },

        /**
         * Render Smart Buttons.
         *
         * @param {HTMLElement} element
         * @return {*}
         */
        render: function (element) {
            var buttonsConfig;

            if (typeof this.paypal === 'undefined' || !this.paypal.Buttons) {
                return null;
            }

            if (element) {
                this.element = element;
            }

            buttonsConfig = {
                element: this.element,
                paymentRequest: this.paymentRequest,
                style: this.styles,
                onClick: this.onClick,
                createOrder: this.createOrder,
                onApprove: this.onApprove,
                onError: this.onError,
                onCancel: this.onCancel,
                onInit: this.onInit
            };

            if (this.onShippingChange) {
                buttonsConfig.onShippingChange = this.onShippingChange.bind(this);
            }
            if (this.fundingSource) {
                buttonsConfig.fundingSource = this.fundingSource;
                if (this.fundingSource === 'applepay') {
                    buttonsConfig.style = this.mapButtonColorForApplePay(this.styles);
                }
            }

            this.instance = this.paypal.Buttons(buttonsConfig);

            if (this.instance.isEligible()) {
                this.instance.render(this.element);
            }

            return this.instance;
        },

        /**
         * Calls when smart buttons initializing
         */
        onInit: function () {
        },

        /**
         * Calls when user click PayPal button.
         */
        onClick: function () {
        },

        /**
         * Calls before create order.
         *
         * @return {Promise}
         */
        beforeCreateOrder: function () {
            return Promise.resolve();
        },

        /**
         * Create order.
         *
         * @return {Promise}
         */
        createOrder: function (data) {
            this.paymentSource = data['paymentSource'];

            // add location to the order create request
            let orderData = new FormData();
            orderData.append('location', this.location);

            return this.beforeCreateOrder()
                .then(performCreateOrder.bind(this, this.createOrderUrl, data, orderData))
                .then(function (orderData) {
                    return this.afterCreateOrder(orderData);
                }.bind(this)).catch(function (error) {
                    return this.catchCreateOrder(error);
                }.bind(this)).finally(function (error) {
                    return this.finallyCreateOrder(error);
                }.bind(this));
        },

        /**
         * After order created.
         *
         * @param {Object} data
         * @return {*}
         */
        afterCreateOrder: function (data) {
            return data.orderId;
        },

        /**
         * Catch error on order creation.
         */
        catchCreateOrder: function () {
        },

        /**
         * Finally for order creation.
         *
         */
        finallyCreateOrder: function () {
        },

        /**
         * Before authorization call.
         *
         * @return {Promise}
         */
        beforeOnAuthorize: function (data, actions) {
            return Promise.resolve(data);
        },

        /**
         * On payment approve.
         *
         * @param {Object} data
         * @param {Object} actions
         * @return {Promise}
         */
        onApprove: function (data, actions) {
            return this.beforeOnAuthorize(data, actions)
                .then(performAuthorization.bind(this, this.completeOrderUrl))
                .catch(function (error) {
                    return this.catchOnAuthorize(error);
                }.bind(this)).finally(function (error) {
                    return this.finallyOnAuthorize(error);
                }.bind(this));
        },

        /**
         * Catch payment authorization errors.
         */
        catchOnAuthorize: function () {
        },

        /**
         * Finally for payment authorization.
         */
        finallyOnAuthorize: function () {
        },

        /**
         * Calls when shipping address changes..
         *
         * @param {Object} data
         */
        onShippingChange: undefined,

        /**
         * Calls when error happened on PayPal side.
         *
         * @param {Error} error
         */
        onError: function (error) {
            console.log('Error: ', error.message);
        },

        /**
         * Calls when user canceled payment.
         */
        onCancel: function () {}
    });
});

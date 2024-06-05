/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'underscore',
    'jquery',
    'mageUtils',
    'Magento_PaymentServicesPaypal/js/view/payment/paypal-abstract',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/google-pay'
], function (_, $, utils, Component, $t, customerData, ResponseError, GooglePayButton) {
    'use strict';

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypalGooglePay',
            scriptParams: {},
            buttonContainerId: 'google-pay-${ $.uid }',
            template: 'Magento_PaymentServicesPaypal/payment/google-pay',
            paymentsOrderId: null,
            paypalOrderId: null,
            sdkLoaded: null,
            sdkParamsKey: 'googlepay',
            paymentTypeIconTitle: $t('Pay with Google Pay'),
            requestProcessingError: $t('Something went wrong with your request. Please try again later.'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            productFormSelector: '#product_addtocart_form'
        },


        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            _.bindAll(this, 'initGooglePayButton', 'onClick', 'afterUpdateQuote',
                'catchError', 'beforeCreateOrder', 'afterOnAuthorize', 'onCancel');
            config.uid = utils.uniqueid();
            this._super();
            this.element = element;
            this.element.id = this.buttonContainerId;
            this.getSdkParams()
                .then(this.initGooglePayButton)
                .catch(console.log);

            return this;
        },

        initGooglePayButton: function () {
            this.googlePayButton = new GooglePayButton({
                scriptParams: this.sdkParams,
                createOrderUrl: this.createOrderUrl,
                updateQuoteUrl: this.authorizeOrderUrl,
                onClick: this.onClick,
                beforeCreateOrder: this.beforeCreateOrder,
                catchCreateOrder: this.catchError,
                onError: this.catchError,
                buttonContainerId: this.buttonContainerId,
                afterUpdateQuote: this.afterUpdateQuote,
                shippingAddressRequired: !this.isVirtual,
                styles: this.styles,
                afterOnAuthorize: this.afterOnAuthorize,
                onCancel: this.onCancel,
                mode: this.googlePayMode
            });

            this.googlePayButton.sdkLoaded
                .then(this.googlePayButton.initGoogleSDK);
        },

        afterUpdateQuote: function (data) {
            window.location = data.redirectUrl;
            this.showLoader(false);
        },

        onClick: function () {
            var $form = $(this.productFormSelector);

            if ($form.data('mageValidation')) {
                this.formValid = $form.validation('isValid');
            }

            if (this.formValid) {
                this.isErrorDisplayed = false;
                this.showLoader(true);
                this.googlePayButton.createOrder();
            }
        },

        /**
         * Catch errors.
         *
         * @param {*} error
         */
        catchError: function (error) {
            console.log(error);
            this.showLoader(false);

            if (this.isErrorDisplayed) {
                return;
            }

            if (error.hidden === undefined || !error.hidden) {
                this.addMessage(this.requestProcessingError);
            }

            this.isErrorDisplayed = true;
        },

        /**
         * Show/hide loader.
         *
         * @param {Boolean} show
         */
        showLoader: function (show) {
            var event = show ? 'processStart' : 'processStop';

            $('body').trigger(event);
        },

        /**
         * Add message to customer data.
         *
         * @param {String} message
         * @param {String} [type]
         */
        addMessage: function (message, type) {
            type = type || 'error';
            customerData.set('messages', {
                messages: [{
                    type: type,
                    text: message
                }],
                'data_id': Math.floor(Date.now() / 1000)
            });
        },

        /**
         * Before create order.
         *
         * @return {Promise}
         */
        beforeCreateOrder: function () {
            this.isErrorDisplayed = false;

            return new Promise(function (resolve, reject) {
                if (this.formInvalid) {
                    return reject();
                }

                fetch(this.addToCartUrl, {
                    method: 'POST',
                    headers: {},
                    body: new FormData($(this.productFormSelector)[0]),
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (typeof data.success !== 'undefined') {
                        return resolve();
                    }

                    return reject(new ResponseError(data.error));
                }).catch(function () {
                    return reject();
                });
            }.bind(this));
        },

        afterOnAuthorize: function (data) {
            window.location = data.redirectUrl;
            this.showLoader(false);
        },

        /**
         * Redirect to cart on cancel.
         *
         * @param {Object} data
         * @param {Object} actions
         */
        onCancel: function () {
            customerData.invalidate(['cart']);
            window.location = this.cancelUrl;
        }
    });
});

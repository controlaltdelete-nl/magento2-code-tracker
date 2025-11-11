/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'underscore',
    'jquery',
    'mageUtils',
    'scriptLoader',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/smart-buttons',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_PaymentServicesPaypal/js/helpers/remove-paypal-url-token',
    'Magento_PaymentServicesPaypal/js/model/app-switch-data',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'jquery/jquery-storageapi'
], function (_, $, utils, loadSdkScript, Component, $t, customerData,
    removePayPalUrlToken, appSwitchDataModel, ResponseError) {
    'use strict';

    var refreshCustomerData = function (url) {
        // Trigger ajaxComplete event to update customer data
        customerData.onAjaxComplete(
            {},
            {
                type: 'POST',
                url: url
            }
        );
    };

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypalProduct',
            buttonsContainerId: 'smart-buttons-${ $.uid }',
            element: null,
            productFormSelector: '#product_addtocart_form',
            formInvalid: false,
            paymentActionError: $t('Something went wrong with your request. Please try again later.'),
            addToCartUrl: null,
            isErrorDisplayed: false
        },

        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            _.bindAll(this, 'renderButtons', 'onClick', 'onError', 'beforeCreateOrder',
                'afterCreateOrder', 'beforeOnAuthorize', 'onCancel');
            config.uid = utils.uniqueid();
            this._super();
            this.element = element;
            this.element.id = this.buttonsContainerId;
            this.sdkLoaded = this.getSdkParams().then(() => {
                return loadSdkScript(this.sdkParams, this.sdkNamespace).then((sdkScript) => {
                    this.paypal = sdkScript;
                });
            });
            this.renderButtons();

            return this;
        },

        /**
         * Render buttons
         */
        renderButtons: function () {
            if (!this.sdkLoaded) {
                return;
            }

            this.sdkLoaded.then(function () {
                let containerSelector = '#' + this.buttonsContainerId;

                if ($(containerSelector).length > 0) {
                    this.setup(containerSelector);
                    this.render();
                } else {
                    console.warn('PayPal button container not found:', containerSelector);
                }
            }.bind(this)).catch(function () {
                console.log('Error: Failed to load PayPal SDK script!');
            });
        },

        /**
         * Show/hide loader.
         *
         * @param {Boolean} show
         */
        showLoader: function (show) {
            let event = show ? 'processStart' : 'processStop';

            $('body').trigger(event);
        },

        /**
         * Add error handing to catch errors with authorizing the order.
         *
         * @param {*} error
         */
        catchOnAuthorize: function (error) {
            this.onError(error);
        },

        /**
         * Add error handling to catch errors on creating the order.
         *
         * @param {*} error
         */
        catchCreateOrder: function (error) {
            this.onError(error);
        },

        /**
         * Catch errors.
         *
         * @param {*} error
         */
        onError: function (error) {
            removePayPalUrlToken();

            this.render(this.element);

            let message = error instanceof ResponseError ? error.message : this.paymentActionError;

            this.showLoader(false);

            if (this.isErrorDisplayed) {
                return;
            }
            this.addMessage(message);
            this.isErrorDisplayed = true;
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
         * Calls when user click PayPal button
         *
         * @param {Object} data
         * @param {Promise} actions
         * @return {Promise}
         */
        onClick: function (data, actions) {
            let productForm = $(this.productFormSelector);

            if (productForm.data('mageValidation')) {
                this.formInvalid = !productForm.validation('isValid');
            }

            if (this.formInvalid) {
                return actions.reject();
            }

            appSwitchDataModel.setData('pageType', this.pageType);

            return actions.resolve();
        },

        /**
         * Set quote created from PDP as Inactive
         */
        setQuoteInactive: function () {
            // Set Quote as inactive to avoid having multiple active quotes for the customer
            return $.ajax({
                type: 'POST',
                url: this.setQuoteAsInactiveUrl
            });
        },

        /**
         * Before create order.
         *
         * @return {Promise}
         */
        beforeCreateOrder: function () {
            this.isErrorDisplayed = false;
            this.showLoader(true);

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
                        refreshCustomerData(this.addToCartUrl);

                        return resolve();
                    }

                    return reject(new ResponseError(data.error));
                }.bind(this)).catch(function () {
                    return reject();
                });
            }.bind(this));
        },

        /**
         * After order id created.
         *
         * @param {Object} res
         * @return {*}
         */
        afterCreateOrder: function (res) {
            if (res.response['is_successful']) {
                refreshCustomerData(this.createOrderUrl);

                return res.response['paypal-order'].id;
            }

            throw new ResponseError(res.response.error);
        },

        /**
         * Before onAuthorize execute
         *
         * @param {Object} data
         * @return {Promise}
         */
        beforeOnAuthorize: function (data) {
            this.showLoader(true);

            return Promise.resolve(data);
        },

        /**
         * Set the quote inactive on cancel
         */
        onCancel: function () {
            removePayPalUrlToken();

            this.render(this.element);

            this.setQuoteInactive()
                .always(() => {
                    this.showLoader(false);
                });
        }
    });
});

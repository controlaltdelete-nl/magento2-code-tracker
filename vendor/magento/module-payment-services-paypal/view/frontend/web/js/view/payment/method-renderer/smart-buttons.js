/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2021 Adobe
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

/* eslint-disable no-undef */
define([
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'underscore',
    'mageUtils',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
    'Magento_PaymentServicesPaypal/js/helpers/remove-paypal-url-token',
    'Magento_PaymentServicesPaypal/js/model/app-switch-data',
    'Magento_PaymentServicesPaypal/js/view/payment/methods/smart-buttons',
    'Magento_PaymentServicesPaypal/js/view/payment/message',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Ui/js/model/messageList',
    'uiRegistry',
    'Magento_Customer/js/customer-data'
], function (
    Component,
    $,
    _,
    utils,
    quote,
    fullScreenLoader,
    $t,
    removePayPalUrlToken,
    appSwitchDataModel,
    SmartButtons,
    Message,
    additionalValidators,
    setBillingAddressAction,
    globalMessageList,
    registry,
    customerData
) {
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
            sdkNamespace: 'paypalCheckoutButtons',
            buttonsContainerId: 'smart-buttons-${ $.uid }',
            payLaterMessageContainerId: 'pay-later-message-${ $.uid }',
            template: 'Magento_PaymentServicesPaypal/payment/smart-buttons',
            isAvailable: false,
            isButtonsRendered: false,
            grandTotalAmount: null,
            paymentsOrderId: null,
            paypalOrderId: null,
            requestProcessingError: $t('Error happened when processing the request. Please try again later.'),
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            paymentTypeIconUrl: window.checkoutConfig.payment['payment_services_paypal_smart_buttons'].paymentTypeIconUrl, // eslint-disable-line max-len
            paymentTypeIconTitle: $t('Pay with PayPal')
        },

        /**
         * @inheritdoc
         */
        initialize: function (config) {
            _.bindAll(this, 'onClick', 'onInit', 'catchError', 'beforeCreateOrder', 'afterCreateOrder');
            config.uid = utils.uniqueid();
            this._super();
            this.initSmartButtons();
            this.initMessage();
            quote.totals.subscribe(function (totals) {
                this.grandTotalAmount(totals['base_grand_total']);
                this.message.updateAmount(totals['base_grand_total']);
            }.bind(this));

            return this;
        },

        /**
         * Initialize observables
         *
         * @returns {Component} Chainable.
         */
        initObservable: function () {
            this._super().observe('grandTotalAmount isAvailable isButtonsRendered');
            this.grandTotalAmount(quote.totals()['base_grand_total']);

            return this;
        },

        /**
         * Create instance of smart buttons.
         */
        initSmartButtons: function () {
            this.buttons = new SmartButtons({
                sdkNamespace: this.sdkNamespace,
                scriptParams: window.checkoutConfig.payment[this.getCode()].sdkParams,
                createOrderUrl: window.checkoutConfig.payment[this.getCode()].createOrderUrl,
                styles: window.checkoutConfig.payment[this.getCode()].buttonStyles,
                onInit: this.onInit,
                onClick: this.onClick,
                beforeCreateOrder: this.beforeCreateOrder,
                afterCreateOrder: this.afterCreateOrder,
                catchCreateOrder: this.catchError,
                onApprove: function () {
                    if (this.hasReturned) {
                        this.setAppSwitchResumeData();
                    }

                    this.placeOrder();
                }.bind(this),
                onError: this.catchError,
                location: window.checkoutConfig.payment[this.getCode()].location,
                appSwitchWhenAvailable: window.checkoutConfig.payment[this.getCode()].appSwitchWhenAvailable
            });
        },

        /**
         * Initialize message component
         */
        initMessage: function () {
            this.message = new Message({
                scriptParams: window.checkoutConfig.payment[this.getCode()].sdkParams,
                element: this.element,
                renderContainer: '#' + this.payLaterMessageContainerId,
                styles: window.checkoutConfig.payment[this.getCode()].messageStyles,
                placement: 'payment',
                amount: this.grandTotalAmount()
            });
        },

        /**
         * Get method code
         *
         * @return {String}
         */
        getCode: function () {
            return 'payment_services_paypal_smart_buttons';
        },

        /**
         * Get method data
         *
         * @return {Object}
         */
        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'payments_order_id': this.paymentsOrderId,
                    'paypal_order_id': this.paypalOrderId,
                    'payment_source': this.buttons.paymentSource
                }
            };
        },

        /**
         * Render buttons
         */
        afterRender: function () {
            this.buttons.sdkLoaded.then(function () {
                this.buttons.setup('#' + this.buttonsContainerId);

                if (this.buttons.instance.hasReturned()) {
                    this.hasReturned = true;
                    this.buttons.instance.resume();
                } else {
                    this.buttons.render();
                }

                this.renderMessage();
                this.isAvailable(!!this.buttons.instance && this.buttons.instance.isEligible());
            }.bind(this)).catch(function () {
                this.isAvailable(false);

                return this.buttons;
            }.bind(this)).finally(function () {
                this.isButtonsRendered(true);
            }.bind(this));
        },

        /**
         * Render message
         */
        renderMessage: function () {
            if (window.checkoutConfig.payment[this.getCode()].canDisplayMessage) {
                this.message.render();
            }
        },

        /**
         * Enable/disable buttons.
         *
         * @param {Object} data
         * @param {Object} actions
         */
        onInit: function (data, actions) {
            if (!this.isPlaceOrderActionAllowed()) {
                actions.disable();
            }

            this.isPlaceOrderActionAllowed.subscribe(function (isAllowed) {
                if (isAllowed) {
                    actions.enable();
                } else {
                    actions.disable();
                }
            });
        },

        /**
         * @inheritdoc
         */
        validate: function () {
            var isShippingValid = true,
                source, shippingAddress;

            if (!this._super()) {
                return false;
            }
            source = registry.get('checkoutProvider');
            shippingAddress = registry.get('index = shippingAddress');

            if (source && shippingAddress) {
                source.set('params.invalid', false);
                if (quote.billingAddress() === null) {
                    this.triggerBillingValidation(source);
                }

                // skip shipping validation if quote is virtual or in-store pickup
                if (!quote.isVirtual() && !quote.shippingMethod()['method_code'] === 'pickup') {
                    isShippingValid = shippingAddress.validateShippingInformation();
                }

                return isShippingValid && !source.get('params.invalid');
            }

            return true;
        },

        /**
         * Trigger billing address validation
         *
         * @param {Object} source
         */
        triggerBillingValidation: function (source) {
            var dataScope = `billingAddress${ window.checkoutConfig.displayBillingOnPaymentMethod ?
                this.getCode() : 'shared'}`;

            source.trigger(`${ dataScope }.data.validate`);

            if (source.get(`${dataScope}.custom_attributes`)) {
                source.trigger(`${dataScope}.custom_attributes.data.validate`);
            }
        },

        /**
         * Validate form onClick
         *
         * @param {Object} data
         * @param {Object} actions
         * @return {*}
         */
        onClick: function (data, actions) {
            if (this.validate() && additionalValidators.validate()) {
                // Add terms data to app switch.
                if (window.checkoutConfig?.checkoutAgreements?.isEnabled) {
                    const agreementsInputPath = '.payment-method._active div.checkout-agreements input';

                    $(agreementsInputPath).each(function (index, element) {
                        const termsData = appSwitchDataModel.getData('terms') || {};
                        termsData[element.id] = element.value;

                        appSwitchDataModel.setData('terms', termsData);
                    });
                }

                const reCaptcha = $('.g-recaptcha:visible');
                const reCaptchaId = reCaptcha && reCaptcha.last().attr('id');

                // Add recaptcha if it exists.
                if (reCaptchaId) {
                    const reCaptchaToken = $(`#${reCaptchaId} [name="g-recaptcha-response"]`).val();

                    appSwitchDataModel.setData('recaptcha', reCaptchaToken);
                }

                return actions.resolve();

            }

            appSwitchDataModel.setData('pageType', this.pageType);

            return actions.reject();
        },

        /**
         * Before order created.
         *
         * @return {Promise}
         */
        beforeCreateOrder: function () {
            return new Promise(function (resolve, reject) {
                setBillingAddressAction(globalMessageList).done(resolve.bind(null, null)).fail(reject);
            });
        },

        /**
         * After order created.
         *
         * @param {Object} data
         * @return {String}
         */
        afterCreateOrder: function (data) {
            if (data.response['paypal-order'] && data.response['paypal-order']['mp_order_id']) {
                refreshCustomerData(window.checkoutConfig.payment[this.getCode()].createOrderUrl);

                this.paymentsOrderId = data.response['paypal-order']['mp_order_id'];
                this.paypalOrderId = data.response['paypal-order'].id;

                appSwitchDataModel.setData('paymentsOrderId', this.paymentsOrderId);
                appSwitchDataModel.setData('paypalOrderId', this.paypalOrderId);
                appSwitchDataModel.setData('paymentSource', this.buttons.paymentSource);

                return this.paypalOrderId;
            }

            throw new Error();
        },

        /**
         * Catch error.
         *
         * @param {Error} error
         */
        catchError: function (error) {
            removePayPalUrlToken();
            this.messageContainer.addErrorMessage({
                message: this.requestProcessingError
            });
            console.log('Error: ', error.message);

            this.afterRender();
        },

        /**
         * Re-initialise checkout data.
         *
         * This is in case the App Switch returns in a new tab.
         */
        setAppSwitchResumeData: function () {
            this.paymentsOrderId = appSwitchDataModel.getData('paymentsOrderId');
            this.paypalOrderId = appSwitchDataModel.getData('paypalOrderId');
            this.buttons.paymentSource = appSwitchDataModel.getData('paymentSource');

            // Add terms data to app switch.
            if (window.checkoutConfig?.checkoutAgreements?.isEnabled) {
                const terms = appSwitchDataModel.getData('terms');

                if (terms) {
                    Object.keys(terms).forEach((term) => {
                        $(`#${term}`).prop('checked', terms[term] === '1');
                    });
                }
            }

            const reCaptcha = $('.g-recaptcha:visible'),
                reCaptchaId = reCaptcha && reCaptcha.last().attr('id');

            if (reCaptchaId) {
                const reCaptchaToken = appSwitchDataModel.getData('recaptcha');

                $(`#${reCaptchaId} [name="g-recaptcha-response"]`).val(reCaptchaToken);
            }

            appSwitchDataModel.clearData();
        }
    });
});

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
    'Magento_PaymentServicesPaypal/js/view/payment/methods/apple-pay',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/cart/totals-processor/default',
    'Magento_Customer/js/model/customer',
], function (_, $, utils, Component, $t, customerData, ResponseError, ApplePayButton, quote, totalsProcessor, customer) {
    'use strict';

    return Component.extend({
        defaults: {
            sdkNamespace: 'paypalApplePay',
            sdkParamsKey: 'applepay',
            buttonContainerId: 'apple-pay-${ $.uid }',
            paymentActionError: $t('Something went wrong with your request. Please try again later.'),
            isErrorDisplayed: false
        },

        /**
         * @inheritdoc
         */
        initialize: function (config, element) {
            _.bindAll(this, 'initApplePayButton', 'onClick', 'afterOnAuthorize',  'afterCreateOrder', 'showPopup', 'cancelApplePay');
            config.uid = utils.uniqueid();
            this._super();
            this.element = element;
            this.element.id = this.buttonContainerId;

            this.getSdkParams()
                .then(this.initApplePayButton)
                .catch(console.log);

            return this;
        },

        initApplePayButton: function () {
            this.applePayButton = new ApplePayButton({
                scriptParams: this.sdkParams,
                createOrderUrl: this.createOrderUrl,
                estimateShippingMethodsWhenLoggedInUrl: this.estimateShippingMethodsWhenLoggedInUrl,
                estimateShippingMethodsWhenGuestUrl: this.estimateShippingMethodsWhenGuestUrl,
                shippingInformationWhenLoggedInUrl: this.shippingInformationWhenLoggedInUrl,
                shippingInformationWhenGuestUrl: this.shippingInformationWhenGuestUrl,
                updatePaypalOrderUrl: this.updatePaypalOrderUrl,
                countriesUrl: this.countriesUrl,
                placeOrderUrl: this.placeOrderUrl,
                showPopup: this.showPopup,
                updateQuoteUrl: this.authorizeOrderUrl,
                onClick: this.onClick,
                afterCreateOrder: this.afterCreateOrder,
                catchCreateOrder: this.catchError,
                onError: this.catchError,
                buttonContainerId: this.buttonContainerId,
                afterOnAuthorize: this.afterOnAuthorize,
                shippingAddressRequired: !this.isVirtual,
                styles: this.styles,
                location: this.pageType,
            });

            $('#' + this.buttonContainerId).on('click', this.onClick);

            this.applePayButton.sdkLoaded
                .then(this.applePayButton.initAppleSDK);
        },

        afterOnAuthorize: function (data) {

            this.applePayButton.showLoaderAsync(true)
            .then(() => {
                fetch(this.placeOrderUrl, {
                    method: 'POST'
                }).then(response => {
                    if (response.redirected && response.url.includes("review")) {
                        throw new Error();
                    }
                    return response.text();
                }).then(result => {
                    if (result) {
                        customerData.invalidate(['cart']);
                        document.open();
                        document.write(result);
                        document.close();
                    }
                })
                    .catch(error => {
                        this.applePayButton.showLoader(false);
                        this.applePayButton.catchError(error);
                    });
            })
            .catch(error => {
                this.catchError(error);
            });
        },

        onClick: function () {
            // Reload customer data to use correct loggedin/guest urls in the applepay button
            // See smart_buttons_minicart.phtml:21-22
            if (this.location === 'minicart') {
                this.fixCustomerData();
            }

            // Show popup with initial order amount from window.checkoutConfig
            // See smart_buttons_minicart.phtml:20
            this.applePayButton.showLoaderAsync(true).then(() => {
                const data = {
                    response: {
                        'paypal-order': {
                            currency_code: window.checkoutConfig.quoteData.base_currency_code,
                            amount: window.checkoutConfig.quoteData.grand_total.toString(),
                        }
                    }
                }
                this.applePayButton.showPopup(data);
            })

            this.isErrorDisplayed = false;
        },

        /**
         * After order created.
         *
         * @param {Object} data
         * @return {String}
         */
        afterCreateOrder: function (data) {
            if (data.response['paypal-order'] && data.response['paypal-order']['mp_order_id']) {
                this.paymentsOrderId = data.response['paypal-order']['mp_order_id'];
                this.paypalOrderId = data.response['paypal-order'].id;
                return this.paypalOrderId;
            }

            throw new Error();
        },

        cancelApplePay: function (){
            customerData.invalidate(['cart']);
            window.location.reload();
        },

        showPopup: function (paymentData) {
            const paymentRequest = {
                countryCode: this.applePayButton.applePayConfig.countryCode,
                merchantCapabilities: this.applePayButton.applePayConfig.merchantCapabilities,
                supportedNetworks: this.applePayButton.applePayConfig.supportedNetworks,
                currencyCode: paymentData.response['paypal-order']['currency_code'],
                requiredShippingContactFields: ["name", "phone", "email", "postalAddress"],
                requiredBillingContactFields: ["postalAddress"],
                total: {
                    label: $t("Summary"),
                    type: "final",
                    amount: Number(paymentData.response['paypal-order']['amount']).toString(),
                }
            };

            // See https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession
            this.applePaySession = new ApplePaySession(this.applePayButton.applePayVersionNumber, paymentRequest);

            this.applePayButton.onApplePayValidateMerchant(this.applePaySession);
            this.applePayButton.onApplePayCancel(this.applePaySession, this.cancelApplePay);
            this.applePayButton.onApplePayShippingContactSelected(this.applePaySession, quote.getQuoteId() , paymentRequest.total, quote.isVirtual());
            this.applePayButton.onApplePayShippingMethodSelectedInCartPage(this.applePaySession, quote.getQuoteId());
            this.applePayButton.onApplePayPaymentAuthorized(this.applePaySession);

            this.applePaySession.begin();
        },

        /**
         * Fix customer data
         *
         * Why do we need this?
         * See: src/app/code/Magento/Customer/view/frontend/web/js/model/customer.js:17
         *
         * When we initialise customer data on the page where the minicart was not rendered yet,
         * the customer data in the "window" object is 'undefined' at first because . This makes this line
         *      var isLoggedIn = ko.observable(window.isCustomerLoggedIn),
         * to create an observable of undefined variable, that does not work in knockout.
         * knockout expects an existing variable to create an observable.
         *
         * Later, when we render minicart and update "window" object with customer data,
         * it's not being picked up by customer.js logic and when try to read the data, it's still undefined,
         * even though it exists in the "window" object.
         *
         * This function forces the customer data to be updated from the "window" object.
         */
        fixCustomerData: function () {
            if (customer.isLoggedIn() === undefined && window.isCustomerLoggedIn !== undefined) {
                customer.setIsLoggedIn(window.isCustomerLoggedIn);
            }

            if (customer.isLoggedIn() && _.isEmpty(customer.customerData)) {
                customer.customerData = window.customerData;
            }
        }
    });
});

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'jquery',
    'mage/translate',
    'uiRegistry',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/view/payment/default',
    'Magento_PaymentServicesPaypal/js/helpers/map-address-to-magento',
    'Magento_PaymentServicesPaypal/js/view/payment/fastlane',
    'Magento_Ui/js/model/messageList',
], function (
    $,
    $t,
    uiRegistry,
    setBillingAddressAction,
    loader,
    additionalValidators,
    quote,
    Component,
    mapAddressToMagento,
    fastlaneModel,
    messageList
) {
    'use strict';

    return Component.extend({
        defaults: {
            code: 'payment_services_paypal_fastlane',
            template: 'Magento_PaymentServicesPaypal/payment/fastlane',
            createOrderUrl: window.checkoutConfig.payment['payment_services_paypal_fastlane'].createOrderUrl,
            paymentTypeIconUrl:  window.checkoutConfig.payment['payment_services_paypal_fastlane'].paymentTypeIconUrl, // eslint-disable-line max-len
            paymentTypeIconTitle: $t('Pay with credit card'),
            location: window.checkoutConfig.payment['payment_services_paypal_fastlane'].location,
            paymentSource: window.checkoutConfig.payment['payment_services_paypal_fastlane'].paymentSource,
            requiresCardDetails: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].requiresCardDetails,
            threeDSMode: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].threeDS,
            getOrderDetailsUrl: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].getOrderDetailsUrl,
            paymentsOrderId: null,
            paypalOrderId: null,
            fastlaneToken: null,
        },

        /**
         * @returns {exports.initialize}
         */
        initialize: async function (config) {
            this._super(config);

            return this;
        },

        afterRender: async function () {
            await fastlaneModel.setup();
            fastlaneModel.renderFastlanePaymentComponent('#payment-services-paypal-fastlane');
        },

        onClick: async function () {
            if (!additionalValidators.validate()) {
                return;
            }

            // Create the order with PayPal
            // Get the token from Fastlane
            // Submit payment
            try {
                const { id, paymentSource: { card: { billingAddress, name } } } = await fastlaneModel.getPaymentToken(),
                    [firstname, ...lastname] = name.split(' '),
                    mappedAddress = mapAddressToMagento({ address: billingAddress }),
                    shippingAddress = quote.shippingAddress();

                // Fastlane doesn't provide a phone number in the billing address so get it from shipping if available.
                if (shippingAddress.telephone) {
                    mappedAddress.telephone = shippingAddress.telephone;
                }

                // Add the firstname and lastname as these aren't within the billing address from Fastlane either.
                mappedAddress.firstname = firstname;
                mappedAddress.lastname = lastname.join(' ');

                quote.billingAddress({...mappedAddress, street: Object.values(mappedAddress.street)});

                if (this.isBillingAddressValid()) {
                    this.fastlaneToken = id;
                    this.placeOrder();
                } else {
                    this.isProcessing = false;
                    messageList.addErrorMessage({
                        message: $t('Your billing address is not valid.')
                    });
                }
            } catch(error) {
                loader.stopLoader();
                messageList.addErrorMessage({
                    message: $t('Cannot validate payment.')
                });
            }
        },

        /**
         * Validates the billing address in the checkout provider.
         *
         * @returns {Boolean}
         */
        isBillingAddressValid() {
            // Load the Braintree payment form.
            const billingAddress = uiRegistry.get('checkoutProvider');

            // Reset the validation.
            billingAddress.set('params.invalid', false);

            // Call validation and also the custom attributes validation if they exist.
            billingAddress.trigger(billingAddress.dataScopePrefix + '.data.validate');

            if (billingAddress.get(billingAddress.dataScopePrefix + '.custom_attributes')) {
                billingAddress.trigger(billingAddress.dataScopePrefix + '.custom_attributes.data.validate');
            }

            return !billingAddress.get('params.invalid');
        },

        getOrderPaymentDetails: function (data) {
            if (!this.requiresCardDetails) {
                return Promise.resolve(data);
            }

            return fetch(`${this.getOrderDetailsUrl}`, {
                method: 'GET'
            }).then((res) => {
                return res.json();
            }).catch(function (err) {
                console.log(
                    'Could not get order details. Proceeding with order placement without card details.',
                    err
                );
                return data;
            });
        },

        /**
         * Check the 3DS configuration and if the payment has passed validation.
         *
         * @param {Object} data
         * @returns {Promise|Error}
         */
        checkThreeDs: function (data) {
            if (!this.threeDSMode) {
                return Promise.resolve(data);
            }

            if (data.liabilityShift === 'POSSIBLE' || data.liabilityShift === undefined) {
                return Promise.resolve(data);
            } else {
                throw new Error('User failed 3DS validation.');
            }
        },

        /** @inheritdoc */
        getData: function () {
            var data = this._super();

            data['additional_data'] = {
                paypal_fastlane_profile: fastlaneModel.profileData() ? 'Yes' : 'No',
                payment_source: this.paymentSource,
                paypal_fastlane_token: this.fastlaneToken
            };

            return data;
        }
    });
});

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* eslint-disable no-undef */
define([
    'jquery',
    'underscore',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_PaymentServicesPaypal/js/view/errors/response-error',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Ui/js/model/messageList',
    'Magento_Vault/js/view/payment/vault-enabler',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_PaymentServicesPaypal/js/lib/script-loader',
    'ko'
], function (
    $,
    _,
    $t,
    Component,
    quote,
    loader,
    ResponseError,
    setBillingAddressAction,
    globalMessageList,
    VaultEnabler,
    additionalValidators,
    scriptLoader,
    ko
) {
    'use strict';

    return Component.extend({
        defaults: {
            isFormValid: false,
            invalidFields: [],
            isAvailable: false,
            isFormRendered: false,
            fields: {
                number: {
                    class: 'card-number-field',
                    label: $t('Credit Card Number'),
                    errorMessage: $t('Please enter a valid credit card number.'),
                    selector: '#${ $.formId } .${ $.fields.number.class }',
                    placeholder: '',
                    showLockIcon: true
                },
                expirationDate: {
                    class: 'expiration-date-field',
                    selector: '#${ $.formId } .expiration-date-field',
                    label: $t('Expiration Date'),
                    errorMessage: $t('Incorrect credit card expiration date.'),
                    placeholder: 'MM/YY'
                },
                cvv: {
                    class: 'cvv-field',
                    selector: '#${ $.formId } .cvv-field',
                    label: $t('Card Security Code'),
                    errorMessage: $t('Please enter a valid credit card security code.'),
                    tooltip: {
                        title: $t('What is this?'),
                        src:  $.cvvImgUrl,
                        contentUnsanitizedHtml: '<img src="${ $.cvvImgUrl }" ' +
                            'alt="${ $.cvvTitle }" title="${ $.cvvTitle }" />'
                    },
                    placeholder: ''
                }
            },
            cardsByCode: {
                "amex": "AE",
                "discover": "DI",
                "elo": "ELO",
                "hiper": "HC",
                "jcb": "JCB",
                "mastercard": "MC",
                "visa": "VI",
            },
            cards: {
                AE: {
                    eligibilityCode: 'amex',
                    typeCode: 'american-express'
                },
                DI: {
                    eligibilityCode: 'discover',
                    typeCode: 'discover'
                },
                ELO: {
                    eligibilityCode: 'elo',
                    typeCode: 'elo'
                },
                HC: {
                    eligibilityCode: 'hiper',
                    typeCode: 'hiper'
                },
                JCB: {
                    eligibilityCode: 'jcb',
                    typeCode: 'jcb'
                },
                MC: {
                    eligibilityCode: 'mastercard',
                    typeCode: 'master-card'
                },
                VI: {
                    eligibilityCode: 'visa',
                    typeCode: 'visa'
                }
            },
            orderCreateErrorMessage: {
                default: $t('Failed to place order. Try again or refresh the page if that does not resolve the issue.'), // eslint-disable-line max-len,
                'POSTAL_CODE_REQUIRED': $t('Postal code is required.'),
                'CITY_REQUIRED': $t('City is required.')
            },
            availableCards: [],
            getOrderDetailsUrl: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].getOrderDetailsUrl, // eslint-disable-line max-len
            requiresCardDetails: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].requiresCardDetails, // eslint-disable-line max-len
            ccIcons: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].ccIcons,
            paymentSource: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].paymentSource,
            cvvImgUrl:  window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].cvvImageUrl,
            isCommerceVaultEnabled: window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].isCommerceVaultEnabled, // eslint-disable-line max-len
            emptyErrorMessage: $t('This is a required field.'),
            paymentTypeIconUrl:  window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].paymentTypeIconUrl, // eslint-disable-line max-len
            paymentTypeIconTitle: $t('Pay with credit card'),
            lockTitle: $t('Secure transaction'),
            cvvTitle: $t('The card security code is a three or four digit number printed on a credit card. Visa, Mastercard, and Discover cards have a three digit code on the card back. American Express cards have a four digit code on the card front.'), // eslint-disable-line max-len
            paymentMethodValidationError: $t('Your payment was not successful. Ensure you have entered your details correctly and try again, or try a different payment method. If you have continued problems, contact the issuing bank for your payment method.'), // eslint-disable-line max-len
            notEligibleErrorMessage: $t('This payment option is currently unavailable.'),
            generalErrorMessage: '${ $.paymentMethodValidationError }',
            placeOrderTitle: $t('Place Order'),
            formId: 'hosted-fields-form',
            template: 'Magento_PaymentServicesPaypal/payment/credit-card',
            ccType: '',
            billingAddress: quote.billingAddress,
            paymentsOrderId: null,
            paypalOrderId: null,
            cardBin: null,
            holderName: null,
            cardLast4: null,
            cardExpiryMonth: null,
            cardExpiryYear: null,
            hostedFields: null,
            shouldCardBeVaulted: false,

            paymentsSdk: null,
            paymentsSdkInitPromise: null,
            isInProgress: ko.observable(false),
        },

        /** @inheritdoc */
        initialize: function () {
            _.bindAll(
                this,
                'onError',
                'getOrderCardDetails'
            );
            this._super();
            this.initPaymentsSDK();
            this.initVaulting();

            return this;
        },

        /**
         * Initialize Payments SDK
         * Load js script and initialize SDK
         */
        initPaymentsSDK: function () {
            this.paymentsSdkInitPromise = new Promise(
                function (resolve, reject) {
                    scriptLoader.loadCustom({url: this.getPaymentsSDKUrl()})
                        .then(function () {
                            const sdkConfig = {
                                storeViewCode: this.getGraphQLStoreCode()
                            }

                            if (this.getGraphQLToken()) {
                                sdkConfig.getCustomerToken = () => this.getGraphQLToken();
                            }

                            if (this.getGraphQLUrl()) {
                                sdkConfig.apiUrl = this.getGraphQLUrl();
                            }

                            this.paymentsSdk = new window.PaymentServicesSDK(sdkConfig);

                            this.paymentsSdk.Payment.init({location: "CHECKOUT"})
                                .then(() => {resolve()})
                                .catch((e) => {reject(e)});
                        }.bind(this)).catch((e) => {reject(e)});
                }.bind(this)
            );
        },

        /**
         * Initialize vaulting
         */
        initVaulting: function () {
            this.vaultEnabler = new VaultEnabler();
            this.vaultEnabler.isActivePaymentTokenEnabler(false);
            this.vaultEnabler.setPaymentCode(window.checkoutConfig.payment[this.getCode()].ccVaultCode);
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super()
                .observe('billingAddress paymentsOrderId paypalOrderId cardBin ' +
                    'holderName cardLast4 cardExpiryMonth cardExpiryYear ' +
                    'ccType isFormValid invalidFields availableCards isAvailable isFormRendered shouldCardBeVaulted');

            return this;
        },

        /** @inheritdoc */
        getCode: function () {
            return 'payment_services_paypal_hosted_fields';
        },

        /** @inheritdoc */
        getData: function () {
            var data = this._super();

            data['additional_data'] = {
                payments_order_id: this.paymentsOrderId(),
                paypal_order_id: this.paypalOrderId(),
                payment_source: this.paymentSource
            };

            if (this.cardBin()) {
                data['additional_data']['cardBin'] = this.cardBin();
            }

            if (this.holderName()) {
                data['additional_data']['holderName'] = this.holderName();
            }

            if (this.cardLast4()) {
                data['additional_data']['cardLast4'] = this.cardLast4();
            }

            if (this.cardExpiryMonth()) {
                data['additional_data']['cardExpiryMonth'] = this.cardExpiryMonth();
            }

            if (this.cardExpiryYear()) {
                data['additional_data']['cardExpiryYear'] = this.cardExpiryYear();
            }

            this.vaultEnabler.visitAdditionalData(data);
            return data;
        },

        /** @inheritdoc */
        afterRender: function () {
            this.$form = $('#' + this.formId);

            this.paymentsSdkInitPromise.then(function () {
                this.isAvailable(this.paymentsSdk.Payment.CreditCard.isAvailable())

                if (!this.isAvailable()) {
                    this.isFormRendered(true);
                    return;
                }

                this.paymentsSdk.Payment.CreditCard.render({
                    fields: {
                        number: {
                            selector: this.fields.number.selector,
                            label: this.fields.number.label,
                            class: this.fields.number.class,
                        },
                        expirationDate: {
                            selector: this.fields.expirationDate.selector,
                            label: this.fields.expirationDate.label,
                            class: this.fields.expirationDate.class,
                        },
                        cvv: {
                            selector: this.fields.cvv.selector,
                            label: this.fields.cvv.label,
                            class: this.fields.cvv.class,
                        },
                    },
                    styles: {
                        input: {
                            color: '#ccc',
                            'font-family': '"Open Sans","Helvetica Neue",Helvetica,Arial,sans-serif',
                            'font-size': '16px',
                            'font-weight': '400'
                        },
                        ':focus': {
                            color: '#333'
                        },
                        '.valid': {
                            color: '#333'
                        }
                    },
                    onRender: this.onRender.bind(this),
                    getCartId: this.getMaskedCardId,
                    onStart: this.onStart.bind(this),
                    onSuccess: this.onSuccess.bind(this),
                    getBillingAddress: this.getBillingAddress.bind(this),
                    getShouldVaultCard: () => this.isCommerceVaultEnabled && this.checkShouldCardBeVaulted(),
                    onValidityChange: this.onValidityChange.bind(this),
                    onCardTypeChange: this.onCardTypeChange.bind(this),
                    onError: this.onError.bind(this),
                    getShouldSetPaymentMethodOnCard: () => false,
                });
            }.bind(this))
                .catch((e) => {
                    console.log('Error initializing Payments SDK', e);
                    this.isFormRendered(true);
                    this.isAvailable(false);
                });
        },

        /**
         * Get masked cart id
         */
        getMaskedCardId: function () {
            return window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].quoteMaskedId;
        },

        /**
         * Get Payments SDK URL to load JS script
         */
        getPaymentsSDKUrl: function () {
            return window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].paymentsSDKUrl;
        },

        /**
         * Get GraphQL store code to use in GraphQL requests
         */
        getGraphQLStoreCode: function () {
            return window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].storeViewCode;
        },

        /**
         * Get GraphQL edpoint
         */
        getGraphQLUrl: function () {
            return window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].graphQLEndpointUrl;
        },

        /**
         * Get GraphQL token for authentication
         */
        getGraphQLToken: function () {
            return window.checkoutConfig.payment['payment_services_paypal_hosted_fields'].oauthToken;
        },

        /**
         * Provide billing address for the order
         */
        getBillingAddress: function () {
            return {
                firstName: this.billingAddress().firstname,
                lastName: this.billingAddress().lastname,
                streetAddress: this.billingAddress().street[0],
                extendedAddress: this.billingAddress().street[1],
                region: this.billingAddress().region,
                locality: this.billingAddress().city,
                postalCode: this.billingAddress().postcode,
                countryCodeAlpha2: this.billingAddress().countryId,
            };
        },

        /**
         * Start callback for Hosted Fields
         * Called when the form is submitted
         *
         * @param preventCheckout
         * @returns {Promise<void>}
         */
        onStart: async function (preventCheckout) {
            if (!this.canProceedWithOrder()) {
                preventCheckout("invalid form");
            }

            loader.startLoader();

            await setBillingAddressAction(globalMessageList);
        },

        /**
         * Success callback for Hosted Fields
         * Called when PP order is created and card details are collected
         *
         * @param data
         */
        onSuccess: function (data) {
            this.paymentsOrderId(data.mpOrderId);
            this.paypalOrderId(data.payPalOrderId);

            this.getOrderCardDetails()
                .then(this.placeOrder.bind(this))
                .catch(this.onError.bind(this));
        },

        /**
         * Called after Hosted Fields are rendered
         *
         * @param hostedFields
         */
        onRender: function (hostedFields)  {
            this.isFormValid(false);
            this.ccType('');
            this.invalidFields([]);

            var cards = hostedFields.getEligibleCards()
                .filter(card => this.cardsByCode[card.code] !== undefined)
                .map(card => this.cardsByCode[card.code]);

            this.availableCards(cards);

            this.$form.off('submit');
            this.$form.on('submit', function (e) {
                e.preventDefault();
                this.isInProgress(true);
                hostedFields.submit()
                    .catch(this.onError.bind(this))
                    .finally(function () {
                        loader.stopLoader();
                        this.isInProgress(false);
                    }.bind(this));
            }.bind(this));

            this.isFormRendered(true);
        },

        /**
         * Validity change handler.
         *
         * @param {Object} hostedFields
         * @param {Object} event
         */
        onValidityChange: function (fields, emittedBy) {
            var valid = _.every(fields, function (field) {return field.isValid});
            var invalidFields = this.invalidFields().filter(field => field.name !== emittedBy);

            if (!valid) {
                if (fields[emittedBy] && !fields[emittedBy].isValid) {
                    invalidFields.push({
                        name: emittedBy,
                        message: fields[emittedBy].isEmpty ? this.emptyErrorMessage : this.fields[emittedBy].errorMessage
                    });
                }
                this.invalidFields(invalidFields)
            }

            this.isFormValid(valid);
            this.isFormValid() && this.invalidFields([]);
        },

        /**
         * Check if field is valid.
         *
         * @param {String} fieldName
         * @return {Boolean}
         */
        isFieldValid: function (fieldName) {
            return !this.invalidFields.findWhere({
                name: fieldName
            });
        },

        /**
         * Get error message for field.
         *
         * @param {String} fieldName
         * @return {String}
         */
        getFieldErrorMessage: function (fieldName) {
            return !this.isFieldValid(fieldName) ? this.invalidFields.findWhere({
                name: fieldName
            }).message : '';
        },

        /**
         * Card type changes handler.
         *
         * @param {Array} a list of cards
         */
        onCardTypeChange: function (cards) {
            var code = '';

            if (cards.length === 1 && this.cardsByCode[cards[0].code]) {
                code = this.cardsByCode[cards[0].code]
            }

            this.ccType(code);
        },

        /**
         * Get order card details
         * Used when Signifyd is enabled and requires card details
         *
         * @param response
         * @returns {Promise<any>|Promise<Awaited<unknown>>}
         */
        getOrderCardDetails: function (response) {
            if (!this.requiresCardDetails) {
                return Promise.resolve(response);
            }

            return fetch(`${this.getOrderDetailsUrl}`, {
                method: 'GET'
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (data.response['is_successful'] && data.response['paypal-order']) {
                    const order = data.response['paypal-order'];

                    this.cardBin(order?.payment_source_details?.card?.bin_details?.bin);
                    this.holderName(order?.payment_source_details?.card?.name);
                    this.cardLast4(order?.payment_source_details?.card?.last_digits);
                    this.cardExpiryMonth(order?.payment_source_details?.card?.card_expiry_month);
                    this.cardExpiryYear(order?.payment_source_details?.card?.card_expiry_year);
                }

                return response;
            }.bind(this)).catch(function (err) {
                console.log(
                    'Could not get order details. Proceeding with order placement without card details',
                    err
                );
                return response;
            });
        },

        /**
         * Error callback for transaction.
         */
        onError: function (error) {
            loader.stopLoader();
            var message = this.generalErrorMessage;

            if (error instanceof ResponseError) {
                message = error.message;
            } else if (error['debug_id']) {
                message = this.paymentMethodValidationError;
            }

            if (this.isOrderCreateError(error)) {
                message = this.parseOrderCreateError(error);
            }

            this.messageContainer.addErrorMessage({
                message: message
            });

            if (error instanceof Error) {
                console.log(error.toString());
            } else {
                console.log('Error' + JSON.stringify(error));
            }
        },

        /**
         * Place order
         * Click event handler for place order button
         */
        placeOrderClick: function () {
            if (this.isPlaceOrderActionAllowed() === true) {
                $('#' + this.formId).trigger('submit');
            }
        },

        /**
         * Check if customer checks the "Save for later" box upon checkout
         *
         * @returns {*}
         */
        checkShouldCardBeVaulted: function () {
            const checked = this.vaultEnabler.isActivePaymentTokenEnabler();

            this.shouldCardBeVaulted(checked);
            return checked;
        },

        /**
         * Check if the form is valid and the order can be placed
         *
         * @returns {*}
         */
        canProceedWithOrder: function () {
            return this.validate()
                && additionalValidators.validate()
                && this.isFormValid()
                && this.isPlaceOrderActionAllowed();
        },

        isOrderCreateError: function (error) {
            return error?.cause?.graphQLErrors
                ?.find(e => e?.path?.join("").indexOf("createPaymentOrder") > -1) !== undefined;
        },

        parseOrderCreateError: function (error) {
            if (this.isErrorCode(error, 'POSTAL_CODE_REQUIRED')) {
                return this.orderCreateErrorMessage.POSTAL_CODE_REQUIRED;
            }

            if (this.isErrorCode(error, 'CITY_REQUIRED')) {
                return this.orderCreateErrorMessage.CITY_REQUIRED;
            }

            return this.orderCreateErrorMessage.default;
        },

        isErrorCode: function (error, code) {
            return error?.cause?.graphQLErrors
                ?.find(e => e?.path?.join("").indexOf("createPaymentOrder") > -1)
                ?.extensions?.debugMessage?.indexOf(code) > -1;
        }

    });
});

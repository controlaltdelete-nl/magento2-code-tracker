define([
    'jquery',
    'knockout',
    'uiRegistry',
    'mage/translate',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Customer/js/model/address-list',
    'Magento_Ui/js/model/messageList',
    'Magento_PaymentServicesPaypal/js/helpers/get-allowed-locations',
    'Magento_PaymentServicesPaypal/js/helpers/map-address-to-fastlane',
    'Magento_PaymentServicesPaypal/js/helpers/map-address-to-magento',
    'scriptLoader'
], function ($, ko, uiRegistry, $t, quote, selectPaymentMethodAction, setShippingInformationAction, checkoutData,
             shippingService, stepNavigator, addressList, messageList,
             getAllowedLocations, mapAddressToFastlane, mapAddressToMagento, loadSdkScript) {
    'use strict';

    return {
        code: 'payment_services_paypal_fastlane',
        clientInstance: null,
        fastlaneInstance: null,
        fastlanePaymentComponent: null,
        fastlaneWatermarkComponent: null,
        deviceData: null,
        runningSetup: null,
        customerContextId: null,
        profileData: ko.observable(null),
        email: null,
        sdkNamespace: 'paypalFastlane',

        /**
         * Creates the Fastlane instance.
         *
         * The Fastlane instance is assigned to this.fastlaneInstance.
         *
         * @returns {void}
         */
        createFastlaneInstance: function () {
            return new Promise((resolve) => {
                const braintreeVersion = '3.116.2';
                const fastlaneRequire = require.config({
                    context: 'fastlane',
                    paths: {
                        braintree: `https://js.braintreegateway.com/web/${braintreeVersion}/js`,
                        fastlane: 'https://www.paypalobjects.com/connect-boba',
                        'fastlane/axo.min': 'https://www.paypalobjects.com/connect-boba/axo',
                    },
                    shim: {
                        'fastlane/axo': {
                            deps: ['braintree/client', 'braintree/hosted-fields'],
                        },
                        'fastlane/axo.min': {
                            deps: ['braintree/client.min', 'braintree/hosted-fields.min'],
                        },
                    },
                });

                fastlaneRequire(['fastlane/axo.min'], () => {
                    (async () => {
                        window.braintree = window.braintree || {};
                        window.braintree.client = await new Promise(resolve => fastlaneRequire(['braintree/client.min'], resolve));
                        window.braintree.hostedFields = await new Promise(resolve => fastlaneRequire(['braintree/hosted-fields.min'], resolve));
                        window.braintree.version = braintreeVersion;

                        await loadSdkScript(window.checkoutConfig.payment[this.getCode()].sdkParams, this.sdkNamespace)

                        const fastlaneInstance = await window[this.sdkNamespace].Connect({
                            shippingAddressOptions: {
                                allowedLocations: getAllowedLocations(),
                            },
                            styles: this.getStyles()
                        });

                        resolve(fastlaneInstance);
                    })();
                });
            });
        },

        /**
         * Return the payment method code.
         *
         * @returns {string}
         */
        getCode: function () {
            return this.code;
        },

        getStyles: function () {
            return {
                root: {
                    backgroundColor: window.checkoutConfig.payment[this.getCode()].styling.rootBackgroundColor,
                    errorColor: window.checkoutConfig.payment[this.getCode()].styling.rootErrorColor,
                    fontFamily: window.checkoutConfig.payment[this.getCode()].styling.rootFontFamily,
                    fontSizeBase: window.checkoutConfig.payment[this.getCode()].styling.rootFontSize,
                    padding: window.checkoutConfig.payment[this.getCode()].styling.rootPadding,
                    primaryColor: window.checkoutConfig.payment[this.getCode()].styling.rootPrimaryColor,
                    textColorBase: window.checkoutConfig.payment[this.getCode()].styling.rootTextColor,
                },
                input: {
                    backgroundColor: window.checkoutConfig.payment[this.getCode()].styling.inputBackgroundColor,
                    borderColor: window.checkoutConfig.payment[this.getCode()].styling.inputBorderColor,
                    borderRadius: window.checkoutConfig.payment[this.getCode()].styling.inputBorderRadius,
                    borderWidth: window.checkoutConfig.payment[this.getCode()].styling.inputBorderWidth,
                    focusBorderColor: window.checkoutConfig.payment[this.getCode()].styling.inputFocusBorderColor,
                    textColorBase: window.checkoutConfig.payment[this.getCode()].styling.inputTextColor,
                }
            };
        },

        /**
         * Setups all the required instances needed for Fastlane.
         *
         * @returns {Promise} A promise that completes once the client, data collector and Fastlane instances
         * have been created.
         */
        setup: async function () {
            // If the Fastlane instance has already been creates then immediately return a completed promise.
            if (this.fastlaneInstance !== null) {
                return Promise.resolve();
            }

            // There are multiple different components that can call the setup function at the same time so this
            // is in place to prevent creating multiple instances.
            if (this.runningSetup) {
                return this.runningSetup;
            }

            this.runningSetup = new Promise(async (resolve) => {
                this.attachStepsListener();

                if (this.fastlaneInstance === null) {
                    this.fastlaneInstance = await this.createFastlaneInstance();
                }

                resolve();
            });

            return this.runningSetup;
        },

        /**
         * Attach a listener on the steps so that going to payment page opens Fastlane by default.
         */
        attachStepsListener: function () {
            stepNavigator.steps.subscribe((steps) => {
                const payment = steps.find(({ code }) => code === 'payment');

                // Check against a few things:
                // 1. The payment step is visible
                // 2. The User has authenticated with Fastlane
                // 3. No other payment method has been selected
                if (payment.isVisible() && this.profileData() && !quote.paymentMethod()) {
                    checkoutData.setSelectedPaymentMethod(this.code);
                    selectPaymentMethodAction({ method: this.code });
                }
            });
        },

        /**
         * Run the lookup for an email address within Fastlane.
         *
         * This will reset data within this.profileData and this.customerContextId and then trigger
         * another authentication if a new account is found.
         *
         * @param {string} email
         * @returns {void}
         */
        lookupCustomerByEmail: async function (email) {
            // Early return if we haven't run setup and got a valid Fastlane instance.
            if (!this.fastlaneInstance) {
                return;
            }

            try {
                this.showLoader(true);

                // When we perform another lookup destroy all existing data.
                this.profileData(null);
                this.customerContextId = null;

                // Lookup the new User.
                const { customerContextId } = await this.fastlaneInstance?.identity?.lookupCustomerByEmail(email) || {};

                this.showLoader(false);

                this.customerContextId = customerContextId;

                // If we have do have an account then trigger the authentication.
                if (this.customerContextId) {
                    return this.triggerAuthenticationFlow();
                }
            } catch (error) {
                console.warn(error);
                this.showLoader(false);
            }
        },

        /**
         * Checks whether the quote already contains a customer email address and shipping address.
         *
         * @param {Object} profileData - The complete profile data as gathered from Fastlane.
         * @param {Object} [profileData.card] - Optional card data object.
         * @param {Object} [profileData.name] - Optional name data object.
         * @param {Object} [profileData.shippingAddress] - Optional shipping address object.
         * @returns {Boolean}
         */
        quoteHasAddressSaved: function (profileData) {
            return !this.email
                && profileData?.shippingAddress?.address?.addressLine1
                && !quote.shippingAddress().postcode;
        },

        /**
         * Checks whether the User has changed their email address against the quote.
         *
         * @returns {Boolean}
         */
        userHasChangedEmailAddress: function () {
            return this.email && this.email !== quote.guestEmail;
        },

        /**
         * Trigger the authentication flow within Fastlane.
         *
         * Once the User has finished the action the information will be available within this.profileData.
         *
         * @returns {void}
         */
        triggerAuthenticationFlow: async function () {
            // Early return if we haven't run setup and got a valid Fastlane instance.
            if (!this.fastlaneInstance) {
                return;
            }

            this.showLoader(true);
            const { profileData }
                = await this.fastlaneInstance.identity.triggerAuthenticationFlow(this.customerContextId);

            this.showLoader(false);

            // With the account data push it into the required models.
            if (profileData) {
                // Before processing the data we need to check for a few things:
                //   - If there is no email address already but we do have a postcode then don't override as this will
                //     be a custom address set by the User.
                //   - If we have an email address stored but it doesn't match with the quote then the User must have
                //     updated their email address and authenticated so process their new profile data.
                if (this.quoteHasAddressSaved(profileData) || this.userHasChangedEmailAddress()) {
                    this.processUserData(profileData);
                }

                // Store the current email address and profile data.
                this.email = quote.guestEmail;
                this.profileData(profileData);
            }
        },

        /**
         * Renders the Fastlane card component inside the given css selector.
         *
         * @param {string} selector The css selector where to render the card component.
         * @returns {void}
         */
        renderFastlanePaymentComponent: async function (selector) {
            // Early return if we haven't run setup and got a valid Fastlane instance.
            if (!this.fastlaneInstance) {
                return;
            }

            // If there is no customer context ID they must have reloaded on the payment page so trigger the
            // authentication here again.
            if (this.customerContextId === null) {
                await this.lookupCustomerByEmail(quote.guestEmail);
            }

            const shippingAddress = mapAddressToFastlane(quote.shippingAddress()),
                fields = {
                    phoneNumber: {
                        prefill: this.profileData()?.shippingAddress?.phoneNumber
                            || quote.shippingAddress().telephone || ''
                    },
                    cardholderName: {
                        // Enabled flag currently not available within Fastlane SDK but leaving functionality
                        // in as it will be in a later release.
                        // enabled: window.checkoutConfig.fastlane.show_cardholder_name,
                        prefill: shippingAddress.firstName && shippingAddress.lastName
                            ? `${shippingAddress.firstName} ${shippingAddress.lastName}` : ''
                    }
                },
                styles = this.getStyles();

            this.fastlanePaymentComponent = await this.fastlaneInstance
                .FastlanePaymentComponent({ fields, shippingAddress, styles });
            this.fastlanePaymentComponent.render(selector);
        },

        /**
         * Shows the address Fastlane address selector.
         *
         * When the User selects a new address this will automatically call `processUserData` with the updated
         * information.
         *
         * @returns {void}
         */
        displayChangeShipping: async function () {
            // Early return if we haven't run setup and got a valid Fastlane instance.
            if (!this.fastlaneInstance?.profile) {
                return;
            }

            this.showLoader(true);

            const {
                selectionChanged,
                selectedAddress
            } = await this.fastlaneInstance.profile.showShippingAddressSelector();

            if (selectionChanged) {
                this.processUserData({ shippingAddress: selectedAddress });
            }

            this.showLoader(false);
        },

        /**
         * Renders the Fastlane watermark into the given selector.
         * @param {string} selector The css selector where to render the watermark component.
         * @returns {void}
         */
        renderFastlaneWatermarkComponent: async function (selector) {
            // Early return if we haven't run setup and got a valid Fastlane instance.
            if (!this.fastlaneInstance) {
                return;
            }

            // Make sure the element still exists.
            if (!document.querySelector(selector)) {
                return;
            }

            this.fastlaneWatermarkComponent = await this.fastlaneInstance.FastlaneWatermarkComponent({
                includeAdditionalInfo: true
            });
            this.fastlaneWatermarkComponent.render(selector);
        },

        /**
         * Handles all of the data from Fastlane and populating that into Adobe Commerce checkout models.
         *
         * @param {Object} profileData - The complete profile data as gathered from Fastlane.
         * @param {Object} [profileData.card] - Optional card data object.
         * @param {Object} [profileData.name] - Optional name data object.
         * @param {Object} [profileData.shippingAddress] - Optional shipping address object.
         * @returns {void}
         */
        processUserData: async function (profileData) {
            // Clean up any existing subscriptions so we don't add more than one at a time.
            if (this.shippingServiceSubscription) {
                this.shippingServiceSubscription.dispose();
            }

            try {
                // If the quote is virtual then open paypal and stop.
                if (quote.isVirtual()) {
                    selectPaymentMethodAction({ method: this.code });
                    return;
                }

                const shippingAddress = uiRegistry.get('checkout.steps.shipping-step.shippingAddress'),
                    mappedAddress = mapAddressToMagento(profileData.shippingAddress);

                // Subscribe to get the updated shipping rates.
                this.shippingServiceSubscription = shippingService.getShippingRates().subscribe(function (rates) {
                    this.shippingServiceSubscription.dispose();

                    // Filter out the "instore" option as we cannot select the pickup location
                    rates = rates.filter(function (rate) {
                        return rate.carrier_code !== 'instore';
                    });

                    if (!rates || !rates.length) {
                        this.redirectToShipping();
                        return;
                    }

                    // If the shipping address is valid and we have some shipping rates then set the data to quote.
                    if (!shippingAddress.source.get('params.invalid') && rates && rates[0]) {
                        shippingAddress.selectShippingMethod(rates[0]);

                        setShippingInformationAction().done(
                            function () {
                                // If we are on the first step of the checkout then we can skip to the next step.
                                if (stepNavigator.getActiveItemIndex() === 0) {
                                    stepNavigator.next();
                                }
                            }
                        );
                    }
                }.bind(this));

                // Push mapped address into the correct models which will trigger getting the updated shipping methods.
                addressList.push(mappedAddress);
                this.addAddressToCheckoutProvider(mappedAddress);

                shippingAddress.source.set('params.invalid', false);
                shippingAddress.triggerShippingDataValidateEvent();

                if (shippingAddress.source.get('params.invalid')) {
                    this.redirectToShipping();
                }
            } catch {
                messageList.addErrorMessage({
                    message: $t('The selected shipping address is not available to be used. Please enter a new one.')
                });
                this.showLoader(false);
            }
        },

        /**
         * Redirects the User back to the shipping step.
         * @returns {void}
         */
        redirectToShipping: function () {
            stepNavigator.setHash('shipping');
            this.showLoader(false);
        },

        /**
         * Push the new address into the checkout provider.
         *
         * @param {Object} address - A complete address object in the correct Adobe Commerce format.
         * @returns {void}
         */
        addAddressToCheckoutProvider: function (address) {
            const checkoutProvider = uiRegistry.get('checkoutProvider');
            const billingAddress = uiRegistry.get('checkout.steps.billing-step.payment.payments-list.paypal_billing_agreement-form');

            checkoutProvider.set(
                'shippingAddress',
                address
            );

            // If the billing address is set to be the same as the shipping then update the billing address with
            // the same changed address.
            if (billingAddress && billingAddress.isAddressSameAsShipping()) {
                quote.shippingAddress({ ...address, street: Object.values(address.street) });
                quote.billingAddress({ ...address, street: Object.values(address.street) });
            }
        },

        /**
         * Get the payment token.
         *
         * @returns {Promise}
         */
        getPaymentToken: function () {
            if (!this.fastlanePaymentComponent) {
                const error = new Error();

                error.name = 'paypal_paypal:undefined_component';
                throw error;
            }

            return this.fastlanePaymentComponent.getPaymentToken();
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
    };
});

define([
    'uiComponent',
    'Magento_PaymentServicesPaypal/js/helpers/is-fastlane-available',
    'Magento_PaymentServicesPaypal/js/view/payment/fastlane'
], function (Component, isFastlaneAvailable, fastlaneModel) {
    'use strict';

    return Component.extend({
        profileData: null,

        defaults: {
            template: 'Magento_PaymentServicesPaypal/form/element/change-address'
        },

        /**
         * Initialise the change address component.
         *
         * @returns {Object} Chainable.
         */
        initialize: function () {
            this._super();

            // Early return if Fastlane is not available
            if (!isFastlaneAvailable()) {
                return this;
            }

            this.profileData = fastlaneModel.profileData;

            return this;
        },

        /**
         * Display the shipping address modal from Fastlane.
         *
         * @returns {void}
         */
        displayChangeShipping: async function () {
            fastlaneModel.displayChangeShipping();
        }
    });
});

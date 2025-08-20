define([
    'knockout',
    'uiComponent',
    'Magento_PaymentServicesPaypal/js/helpers/is-fastlane-available',
    'Magento_PaymentServicesPaypal/js/view/payment/fastlane'
], function (ko, Component, isFastlaneAvailable, fastlaneModel) {
    'use strict';

    return Component.extend({
        profileData: null,

        defaults: {
            template: 'Magento_PaymentServicesPaypal/form/element/powered-by'
        },

        /**
         * Initialise the watermark component.
         *
         * @returns {Object} Chainable.
         */
        initialize: function (config) {
            this._super(config);

            this.id = config.id;
            this.profileData = fastlaneModel.profileData;
            this.isVisible = ko.observable(false);

            // Add subscription to profile data changes so we try to render again if needed.
            this.profileData.subscribe(this.renderWatermark.bind(this));

            return this;
        },

        /**
         * Gets whether the watermark is enabled for the email address section.
         *
         * @returns {Boolean}
         */
        isEmailWatermarkEnabled: function () {
            return this.id === 'paypal-fastlane-email-watermark'
                && window.checkoutConfig.payment.payment_services_paypal_fastlane.messaging;
        },

        /**
         * Gets whether the watermark is enabled for the shipping address section.
         *
         * @returns {Boolean}
         */
        isShippingWatermarkEnabled: function () {
            return this.id !== 'paypal-fastlane-email-watermark' && !!this.profileData();
        },

        shouldRenderWatermark: async function () {
            // Early return if Fastlane is not available.
            if (!isFastlaneAvailable()) {
                return false;
            }

            await fastlaneModel.setup();

            // Fastlane Watermark should be rendered based on the following:
            //   - Email watermark is based on the branding configuration
            //   - All others are based on whether we have profile data
            const shouldRender = this.isEmailWatermarkEnabled() || this.isShippingWatermarkEnabled();

            this.isVisible(shouldRender);
        },

        renderWatermark: async function () {
            await this.shouldRenderWatermark();

            if (this.isVisible()) {
                fastlaneModel.renderFastlaneWatermarkComponent(`#${this.id}`);
            }
        }
    });
});

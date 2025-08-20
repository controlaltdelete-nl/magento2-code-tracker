define([
    'Magento_Checkout/js/model/step-navigator',
    'Magento_PaymentServicesPaypal/js/helpers/is-fastlane-available',
    'Magento_PaymentServicesPaypal/js/view/payment/fastlane'
], function (stepsNavigator, isFastlaneAvailable, fastlaneModel) {
    'use strict';

    var mixin = {
        shippingServiceSubscription: null,

        /**
         * Add mixin to the checkEmailAvailability so we can trigger Fastlane.
         */
        checkEmailAvailability: async function () {
            this._super();

            // Early return if this is not the shipping address email input.
            if (this.name !== 'checkout.steps.shipping-step.shippingAddress.customer-email') {
                return;
            }

            // Early return if Fastlane is not available
            if (!isFastlaneAvailable()) {
                return;
            }

            // Early return if we are already on the payment page.
            if (stepsNavigator.getActiveItemIndex() !== 0) {
                return;
            }

            await fastlaneModel.setup();

            // Check the entered email against Fastlane to see if we have an account.
            fastlaneModel.lookupCustomerByEmail(this.email());
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});

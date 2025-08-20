define(['Magento_Customer/js/model/customer'], function (customer) {
    'use strict';

    /**
     * Small helper to check if Fastlane is enabled and the User is NOT logged in.
     *
     * @retuns {boolean}
     */
    return function () {
        return window.checkoutConfig.payment.payment_services_paypal_fastlane?.isVisible && !customer.isLoggedIn();
    };
});

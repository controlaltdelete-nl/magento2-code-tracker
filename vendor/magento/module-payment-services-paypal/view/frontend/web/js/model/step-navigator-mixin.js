/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_PaymentServicesPaypal/js/model/app-switch-data'
], function (wrapper, quote, appSwitchDataModel) {
    'use strict';

    let mixin = {
        handleHash: function (originalFn) {
            var hashString = window.location.hash.replace('#', '');

            // If the URL hash contains `onApprove` or 'onCancel' or 'token'
            // then we've come from PayPal App Switch and the hash isn't
            // going to match with any registered step.
            if (hashString.includes('onApprove')
                || hashString.includes('onCancel')
                || hashString.includes('token')
            ) {
                // If this is a virtual quote then set the payment step to visible.
                if (quote.isVirtual()) {
                    const paymentStep = this.steps().find(({ code }) => code === 'payment');

                    paymentStep.isVisible(true);
                    return;
                }

                // Check if the User has come from the end of the checkout.
                // If so then take them back there.
                if (appSwitchDataModel.getData('paymentsOrderId')) {
                    const paymentStep = this.steps().find(({ code }) => code === 'payment');

                    paymentStep.navigate(paymentStep);
                    return;
                }

                const firstStep = this.steps().sort(this.sortItems)?.[0];

                if (firstStep) {
                    firstStep.navigate(firstStep);

                    return;
                }
            }

            return originalFn();
        }
    };

    return function (target) {
        return wrapper.extend(target, mixin);
    };
});

define(['uiRegistry'], function (uiRegistry) {
    'use strict';

    /**
     * Returns the integer count of the number of street lines available on a customer address.
     *
     * @returns {number}
     */
    return function () {
        const shippingAddress = uiRegistry.get('checkout.steps.shipping-step.shippingAddress');
        return Object.values(shippingAddress.source.shippingAddress.street).length;
    };
});

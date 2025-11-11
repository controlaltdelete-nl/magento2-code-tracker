define(function () {
    'use strict';

    var mixin = {
        /**
         * When changing the shipping method store it within checkoutConfig to support PayPal app switch.
         *
         * @param {*} shippingMethod
         */
        selectShippingMethod: function (shippingMethod) {
            this._super(shippingMethod);

            window.checkoutConfig.selectedShippingMethod = shippingMethod;
        },
    };

    return function (shipping) {
        return shipping.extend(mixin);
    };
});

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list',
    'Magento_PaymentServicesPaypal/js/helpers/is-fastlane-available'
], function (Component, rendererList, isFastlaneAvailable) {
    'use strict';

    const cardField = isFastlaneAvailable() ? {
        type: 'payment_services_paypal_fastlane',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/fastlane'
    } : {
        type: 'payment_services_paypal_hosted_fields',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/hosted-fields'
    };

    rendererList.push({
        type: 'payment_services_paypal_smart_buttons',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/smart-buttons'
    }, {
        type: 'payment_services_paypal_apple_pay',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/apple-pay'
    }, {
        type: 'payment_services_paypal_google_pay',
        component: 'Magento_PaymentServicesPaypal/js/view/payment/method-renderer/google-pay'
    }, cardField);

    return Component.extend({});
});

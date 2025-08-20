/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

var config = {
    map: {
        '*': {
            'Magento_Vault/js/view/payment/vault': 'Magento_PaymentServicesPaypal/js/view/payment/vault'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/payment-service': {
                'Magento_PaymentServicesPaypal/js/model/payment-service-mixin': true
            },
            'Magento_Checkout/js/view/form/element/email': {
                'Magento_PaymentServicesPaypal/js/view/form/element/email-mixin': true
            },
            'Magento_Checkout/js/view/shipping-information': {
                'Magento_PaymentServicesPaypal/js/view/shipping-information-mixin': true
            }
        }
    },
    paths: {
        'fastlane/axo.min': 'https://www.paypalobjects.com/connect-boba/axo'
    }
};

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'mage/translate',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'Magento_PaymentServicesPaypal/js/helpers/map-message-styles',
    'paypalMessageConfigurator'
], function ($, $t, Component, modal, mapMessageStyles) {
    'use strict';

    return Component.extend({
        defaults: {
            configFieldSelector: '.paylater-message-configurator-config',
            configFieldSelectorInherit: '[id$="solutions_magento_payments_legacy_smart_buttons_paylater_message_configurator_inherit"]',
            initConfiguratorValuesField: '.paylater-message-configurator-config-load',
            initConfiguratorValuesFieldInherit: '[id$="solutions_magento_payments_legacy_smart_buttons_paylater_message_configurator_load_inherit"]'
        },

        /**
         * Initialize styling configurator script
         *
         */
        initialize: function () {
            this._super();
            this.onScriptLoad();
        },

        /**
         * Callback function to execute after the PayPal script has loaded
         */
        onScriptLoad: function () {
            const savedConfig = this.getSavedConfig();

            let modalOptions = {
                type: 'popup',
                responsive: false,
                innerScroll: true,
                buttons: [{
                    text: $.mage.__('Close'),
                    class: 'action secondary',
                    click: function () {
                        this.closeModal();
                    }
                }]
            };

            let configuratorModal = $('#configurator-modal');
            modal(modalOptions, configuratorModal);

            $('#open-modal').on('click', function (event) {
                event.preventDefault();
                configuratorModal.modal('openModal');

                // Replace the button text to be more appropriate of behaviour.
                $('#configurator-publishButton').text($t('Save changes'));
            });

            window.merchantConfigurators?.Messaging({
                config: savedConfig, // Use empty object for new users or existing configuration for return users
                locale: this.storeLocale,
                merchantIdentifier: this.merchantId,
                partnerClientId: this.partnerClientId,
                partnerName: this.partnerName,
                bnCode: this.bnCode,
                onSave: (outputConfig) => {
                    configuratorModal.modal('closeModal');

                    $(this.initConfiguratorValuesField)[0].value = JSON.stringify(outputConfig.config);
                    let mappedConfigStyles = mapMessageStyles(outputConfig.config);

                    $(this.configFieldSelector)[0].value = JSON.stringify(mappedConfigStyles);

                    this.setSystemValue();
                },
                placements: ['product', 'cart', 'checkout']
            });
        },

        /**
         * Get saved config values
         *
         * @returns {any}
         */
        getSavedConfig: function () {
            if ($(this.initConfiguratorValuesField).val()) {
                return JSON.parse($(this.initConfiguratorValuesField)[0].value);
            }
        },

        /**
         * Set system values
         */
        setSystemValue: function () {
            const value = $(this.configFieldSelector).val() || $(this.initConfiguratorValuesField).val(),
                systemValue = $(this.configFieldSelectorInherit).prop('checked')
                    || $(this.initConfiguratorValuesFieldInherit).prop('checked');

            if (value && systemValue || !value && !systemValue) {
                $(this.configFieldSelectorInherit).trigger('click');
                $(this.initConfiguratorValuesFieldInherit).trigger('click');
            }
        }
    });
});

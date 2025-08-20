require(['jquery', 'domReady!'], function ($) {
    'use strict';
    const addPlaceholders = () => {
        /* eslint-disable max-len */
        const placeholderFields = {
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_background_color"]': '#FFFFFF',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_error_color"]': '#D9360B',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_font_family"]': 'Paypal-Open, sans-serif',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_font_size"]': '16px',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_padding"]': '4px',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_primary_color"]': '#0057FF',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_text_color"]': '#010B0D',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_background_color"]': '#FFFFFF',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_border_color"]': '#DADDDD',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_border_radius"]': '4px',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_border_width"]': '1px',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_focus_border_color"]': '#0057FF',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_text_color"]': '#010B0D'
        };
        /* eslint-enable max-len */

        Object.keys(placeholderFields).forEach((id) => {
            $(id).attr('placeholder',  placeholderFields[id]);
        });
    },

    addColorInputs = () => {
        /* eslint-disable max-len */
        const colorFields = [
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_background_color"]',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_error_color"]',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_primary_color"]',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_root_text_color"]',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_background_color"]',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_border_color"]',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_focus_border_color"]',
            'input[id$="magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_input_text_color"]'
        ];
        /* eslint-enable max-len */

        $(colorFields).each((index, colorField) => {
            const $colorPicker = $('<input type="color" />'),
                $colorField = $(colorField),
                placeholderValue =  $colorField.attr('placeholder');

            $colorField.on('change', (event) => $colorPicker.val(event.target.value || placeholderValue));
            $colorPicker.on('input', (event) => $colorField.val(event.target.value));

            $colorPicker.val($colorField.val() || placeholderValue);
            $colorPicker.insertAfter(colorField);
        });
    };

    addPlaceholders();
    addColorInputs();
});

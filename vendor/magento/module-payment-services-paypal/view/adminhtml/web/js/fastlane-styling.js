require(['jquery', 'domReady!'], function ($) {
    'use strict';
    const idPrefix = 'magento_payments_legacy_fastlane_fastlane_styling_fastlane_styling_';

    const defaultValues = {
        light: {
            root: {
                backgroundColor: "#ffffff",
                errorColor: "#d9360b",
                fontFamily: "Paypal-Open, sans-serif",
                fontSizeBase: "16px",
                padding: "4px",
                primaryColor: "#0057ff",
                textColorBase: "#010b0d"
            },
            input: {
                backgroundColor: "#ffffff",
                borderColor: "#dadddd",
                borderRadius: "4px",
                borderWidth: "1px",
                focusBorderColor: "#0057ff",
                textColorBase: "#010b0d"
            }
        },
        dark: {
            root: {
                backgroundColor: "#010B0D",
                errorColor: "#f30a0a",
                fontFamily: "Paypal-Open, sans-serif",
                fontSizeBase: "16px",
                padding: "4px",
                primaryColor: "#00d7fb",
                textColorBase: "#ffffff"
            },
            input: {
                backgroundColor: "#010b0d",
                borderColor: "#576062",
                borderRadius: "4px",
                borderWidth: "1px",
                focusBorderColor: "#00d7fb",
                textColorBase: "#ffffff"
            }
        }
    };

    const addPlaceholders = (theme, reset = false) => {
        const placeholderFields = {
            [`input[id$="${idPrefix}root_background_color"]`]: defaultValues[theme].root.backgroundColor,
            [`input[id$="${idPrefix}root_error_color"]`]: defaultValues[theme].root.errorColor,
            [`input[id$="${idPrefix}root_font_family"]`]: defaultValues[theme].root.fontFamily,
            [`input[id$="${idPrefix}root_font_size"]`]: defaultValues[theme].root.fontSizeBase,
            [`input[id$="${idPrefix}root_padding"]`]: defaultValues[theme].root.padding,
            [`input[id$="${idPrefix}root_primary_color"]`]: defaultValues[theme].root.primaryColor,
            [`input[id$="${idPrefix}root_text_color"]`]: defaultValues[theme].root.textColorBase,
            [`input[id$="${idPrefix}input_background_color"]`]: defaultValues[theme].input.backgroundColor,
            [`input[id$="${idPrefix}input_border_color"]`]: defaultValues[theme].input.borderColor,
            [`input[id$="${idPrefix}input_border_radius"]`]: defaultValues[theme].input.borderRadius,
            [`input[id$="${idPrefix}input_border_width"]`]: defaultValues[theme].input.borderWidth,
            [`input[id$="${idPrefix}input_focus_border_color"]`]: defaultValues[theme].input.borderColor,
            [`input[id$="${idPrefix}input_text_color"]`]: defaultValues[theme].input.textColorBase
        };

        Object.keys(placeholderFields).forEach((id) => {
            const $field = $(id);

            if (reset) {
                $field.val(null);
            }

            $field.attr('placeholder',  placeholderFields[id]);
            $field.trigger('change');
        });
    },

    addColorInputs = () => {
        const colorFields = [
            `input[id$="${idPrefix}root_background_color"]`,
            `input[id$="${idPrefix}root_error_color"]`,
            `input[id$="${idPrefix}root_primary_color"]`,
            `input[id$="${idPrefix}root_text_color"]`,
            `input[id$="${idPrefix}input_background_color"]`,
            `input[id$="${idPrefix}input_border_color"]`,
            `input[id$="${idPrefix}input_focus_border_color"]`,
            `input[id$="${idPrefix}input_text_color"]`
        ];

        $(colorFields).each((index, colorField) => {
            const $colorPicker = $('<input type="color" />'),
                $colorField = $(colorField);

            $colorField.on('change', (event) => {
                const placeholderValue =  $colorField.attr('placeholder');
                $colorPicker.val(event.target.value || placeholderValue);
            });

            $colorPicker.on('input', (event) => $colorField.val(event.target.value));

            $colorPicker.val($colorField.val() || $colorField.attr('placeholder'));
            $colorPicker.insertAfter(colorField);
        });
    };

    const $theme = $(`select[id$="magento_payments_legacy_fastlane_fastlane_styling_theme"]`);

    addPlaceholders($theme.val());
    addColorInputs();

    $theme.on('change', () => {
        addPlaceholders($theme.val());
    });

    $('[id$="magento_payments_legacy_fastlane_fastlane_styling_reset"] button').on('click', (event) => {
        event.preventDefault();

        addPlaceholders($theme.val(), true);
    });
});

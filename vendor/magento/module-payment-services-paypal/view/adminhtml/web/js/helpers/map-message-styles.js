/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define(function () {
    'use strict';

    return function (configuratorStyles) {
        const result = {};

        for (const key in configuratorStyles) {
            if (configuratorStyles.hasOwnProperty(key)) {
                const config = configuratorStyles[key];

                if (config.status === 'disabled') {
                    continue;
                }

                result[key] = {
                    layout: config.layout,
                    logo: {
                        position: config['logo-position'],
                        type: config['logo-type']
                    },
                    text: {
                        align: 'left',
                        color: config['text-color'],
                        size: parseInt(config['text-size'], 10)
                    }
                };
            }
        }

        return result;
    };
});

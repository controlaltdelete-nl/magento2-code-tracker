define(['uiRegistry'], function (uiRegistry) {
    'use strict';

    /**
     * Get the available list of countries as defined in configuration.
     *
     * @returns {Array}
     */
    return function () {
        const countries = uiRegistry.get('checkoutProvider').get('dictionaries.country_id'),
            allowedLocations = countries.map(({ value }) => value).filter((value) => value && value !== 'delimiter');

        return allowedLocations;
    };
});

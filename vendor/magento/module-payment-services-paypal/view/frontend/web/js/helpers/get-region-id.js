define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    /**
     * Converts a region string into the associated region ID.
     *
     * @returns {number}
     */
    return function (countryCode, administrativeArea) {
        const countryData = customerData.get('directory-data')(),
            country = countryData[countryCode];

            if (country?.regions) {
                const regionIds = Object.keys(country.regions);

                return regionIds.find((regionId) => {
                    return country.regions[regionId].code === administrativeArea;
                }) || 0;
            }

            return 0;
    };
});

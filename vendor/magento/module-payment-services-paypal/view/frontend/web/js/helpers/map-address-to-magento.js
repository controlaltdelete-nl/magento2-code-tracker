define([
    'Magento_Checkout/js/model/new-customer-address',
    'Magento_PaymentServicesPaypal/js/helpers/get-allowed-locations',
    'Magento_PaymentServicesPaypal/js/helpers/get-street-line-count',
    'Magento_PaymentServicesPaypal/js/helpers/get-region-id'
], function (Address, getAllowedLocations, getStreetLineCount, getRegionId) {
    'use strict';

    /**
     * Creates an array of empty strings with the length being the set street lines for a customer address.
     *
     * @returns {Array}
     */
    const createEmptyStreetArray = () => {
        const streetLines = getStreetLineCount();
        return Array.apply(null, Array(streetLines))
            .reduce((prev, curr, index) => { prev[index] = ''; return prev; }, {});
    };

    /**
     * Fastlane provides an address object that isn't in the correct Adobe Commerce format so map it to the
     * correct format.
     *
     * @param {Object} address - An address object as gathered from Fastlane.
     * @returns {Object} - A correctly mapped address using 'Magento_Checkout/js/model/new-customer-address' model.
     */
    return function (address) {
        const street = createEmptyStreetArray();

        street[0] = address.address.addressLine1 || address.address.streetAddress;

        if (address.address.addressLine2 || address.address.extendedAddress) {
            street[1] = address.address.addressLine2 || address.address.extendedAddress;
        }

        const mappedAddress = Address({
                region: {
                    region_id: getRegionId(
                        address.address.countryCode || address.address.countryCodeAlpha2,
                        address.address.adminArea1 || address.address.region
                    ),
                    region_code: address.address.adminArea1 || address.address.region,
                    region: address.address.adminArea1 || address.address.region
                },
                company: address.address.company || '',
                country_id: address.address.countryCode || address.address.countryCodeAlpha2,
                street,
                firstname: address.name?.firstName || '',
                lastname: address.name?.lastName || '',
                city: address.address.adminArea2 || address.address.locality,
                telephone: address.phoneNumber?.nationalNumber || '00000000000',
                postcode: address.address.postalCode
            }),
            allowedLocations = getAllowedLocations();

        mappedAddress.country_id = mappedAddress.countryId;
        mappedAddress.region_code = mappedAddress.regionCode;
        mappedAddress.region_id = mappedAddress.regionId;
        mappedAddress.street = street;

        // If the country / region isn't available on this website then throw an error.
        if (!allowedLocations.includes(mappedAddress.countryId)) {
            const error = new Error();

            error.name = 'paypal_fastlane:address_unavailable';
            throw error;
        }

        return mappedAddress;
    };
});

<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model\ShippingCallback;

use Exception;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

class AddressValidator
{
    private const POSTAL_CODE_PATTERN = '/^[a-zA-Z0-9\- ]+$/';

    /**
     * @param AddressInterfaceFactory $addressFactory
     * @param RegionFactory $regionFactory
     * @param AllowedCountries $allowedCountries
     * @param RegionCollectionFactory $regionCollectionFactory
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly RegionFactory $regionFactory,
        private readonly AllowedCountries $allowedCountries,
        private readonly RegionCollectionFactory $regionCollectionFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate and set shipping & billing address
     *
     * @param CartInterface|Quote $quote
     * @param array $shippingAddress
     * @return void
     * @throws LocalizedException
     */
    public function validateAndSetAddress(CartInterface|Quote $quote, array $shippingAddress): void
    {
        $countryId = $shippingAddress['country_code'] ?? null;
        $state = $shippingAddress['admin_area_1'] ?? null;
        $postalCode = $shippingAddress['postal_code'] ?? null;

        $this->validateCountry($countryId);
        $this->validateState($state, $countryId);
        $this->validatePostalCode($postalCode);

        $address = $this->createAddress($shippingAddress, $countryId, $state, $postalCode);

        $quote->setShippingAddress($address);
        $quote->setBillingAddress($address);
        $this->cartRepository->save($quote);
    }

    /**
     * Validate country
     *
     * @param string|null $countryId
     * @return void
     * @throws LocalizedException
     */
    private function validateCountry(?string $countryId): void
    {
        $allowedCountries = $this->allowedCountries->getAllowedCountries();
        if (!in_array($countryId, $allowedCountries)) {
            throw new LocalizedException(__('COUNTRY_ERROR'));
        }
    }

    /**
     * Validate state/region
     *
     * @param string|null $state
     * @param string|null $countryId
     * @return void
     * @throws LocalizedException
     */
    private function validateState(?string $state, ?string $countryId): void
    {
        $allowedCountries = $this->allowedCountries->getAllowedCountries();
        $countries = $this->getCountriesWithPreDefinedRegions($allowedCountries);

        if (in_array($countryId, $countries)) {
            $regionId = $this->getRegionIdByCode($state, $countryId);
            if (!$regionId) {
                throw new LocalizedException(__('STATE_ERROR'));
            }
        }
    }

    /**
     * Validate postal code
     *
     * @param string|null $postalCode
     * @return void
     * @throws LocalizedException
     */
    private function validatePostalCode(?string $postalCode): void
    {
        if ($postalCode === null || !preg_match(self::POSTAL_CODE_PATTERN, $postalCode)) {
            throw new LocalizedException(__('ZIP_ERROR'));
        }
    }

    /**
     * Create address object
     *
     * @param array $shippingAddress
     * @param string $countryId
     * @param string|null $state
     * @param string $postalCode
     * @return AddressInterface
     */
    private function createAddress(
        array $shippingAddress,
        string $countryId,
        ?string $state,
        string $postalCode
    ): AddressInterface {
        $address = $this->addressFactory->create();

        /**
         * PayPal callback request does not contain firstname and lastname that's why random value
         * Like, 'firstname' and 'lastname' have been set to quote address Firstname and Lastname fields.
         *
         * We are setting up the correct values at the time of placing the order so nothing to worry
         */
        $address->setFirstname('firstname');
        $address->setLastname('lastname');
        $address->setTelephone($shippingAddress['telephone'] ?? '00000000');
        $address->setCity($shippingAddress['admin_area_2'] ?? null);
        $address->setCountryId($countryId);
        $address->setPostcode($postalCode);
        $address->setRegionId($this->getRegionIdByCode($state, $countryId));

        return $address;
    }

    /**
     * Get region ID by code
     *
     * @param string|null $regionCode
     * @param string|null $countryId
     * @return int
     */
    private function getRegionIdByCode(?string $regionCode, ?string $countryId): int
    {
        try {
            if ($regionCode === null || $countryId === null) {
                throw new LocalizedException(__('STATE_ERROR'));
            }
            $region = $this->regionFactory->create()->loadByCode($regionCode, $countryId);
        } catch (Exception $exception) {
            $this->logger->error("Failed to find region for given code", [
                'exception_message' => $exception->getMessage(),
                'region_code' => $regionCode
            ]);
            return 0;
        }

        return (int) $region->getRegionId();
    }

    /**
     * Get list of countries which have pre-defined regions/states
     *
     * @param array $allowedCountries
     * @return array
     */
    private function getCountriesWithPreDefinedRegions(array $allowedCountries): array
    {
        $collection = $this->regionCollectionFactory->create()
            ->addFieldToSelect('country_id')
            ->addFieldToFilter('country_id', ['in' => $allowedCountries]);
        $collection->getSelect()->distinct();

        $countries = [];

        if ($collection->getSize() > 0) {
            foreach ($collection as $region) {
                $countries[] = $region->getCountryId();
            }
            $countries = array_unique($countries);
        }

        return $countries;
    }
}

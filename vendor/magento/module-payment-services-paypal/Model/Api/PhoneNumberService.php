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

namespace Magento\PaymentServicesPaypal\Model\Api;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Magento\PaymentServicesPaypal\Api\PhoneNumberServiceInterface;
use Magento\Quote\Model\Quote\Address;

class PhoneNumberService implements PhoneNumberServiceInterface
{
    /**
     * @var PhoneNumberUtil
     */
    private PhoneNumberUtil $phoneNumberUtil;

    /**
     * PhoneNumberService constructor
     */
    public function __construct()
    {
        // phpcs:ignore Magento2.CodeAnalysis.StaticCall
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
    }

    /**
     * Get country ISD calling code
     *
     * @param string $countryCode
     * @return int
     */
    public function getCountryCodeForRegion(string $countryCode): int
    {
        return $this->phoneNumberUtil->getCountryCodeForRegion($countryCode);
    }

    /**
     * Format phone number
     *
     * @param Address $address
     * @return string
     */
    public function formatPhoneNumber(Address $address): string
    {
        $shippingTelephone = $address->getTelephone();
        if (!$shippingTelephone) {
            return '';
        }
        $countryCode = $address->getCountryId();
        try {
            $telephone = $this->phoneNumberUtil->parse($shippingTelephone, $countryCode);

            if ($this->phoneNumberUtil->isValidNumber($telephone)) {
                $phoneNumber = $telephone->getNationalNumber();

                return (string) $phoneNumber;
            } else {
                return $this->formatPhoneNumberThroughPregMatch($shippingTelephone);
            }
        } catch (NumberParseException $e) {
            return $this->formatPhoneNumberThroughPregMatch($shippingTelephone);
        }
    }

    /**
     * Format phone number through preg match pattern
     *
     * @param string $phoneNumber
     * @return string
     */
    private function formatPhoneNumberThroughPregMatch(string $phoneNumber): string
    {
        // Remove everything except digits
        $digitsOnly = preg_replace('/\D+/', '', $phoneNumber);

        // Remove only the first leading zero if present
        if (str_starts_with($digitsOnly, '0')) {
            $digitsOnly = substr($digitsOnly, 1);
        }

        return (string) $digitsOnly;
    }
}

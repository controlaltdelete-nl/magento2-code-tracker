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

namespace Magento\PaymentServicesPaypal\Api;

use Magento\Quote\Model\Quote\Address;

interface PhoneNumberServiceInterface
{
    /**
     * Get country ISD calling code
     *
     * @param string $countryCode
     * @return int
     */
    public function getCountryCodeForRegion(string $countryCode): int;

    /**
     * Format phone number
     *
     * @param Address $address
     * @return string
     */
    public function formatPhoneNumber(Address $address): string;
}

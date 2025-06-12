<?php

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
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

namespace Magento\PaymentServicesPaypal\Helper;

use Magento\PaymentServicesPaypal\Model\Config;

class Util
{
    /**
     * So far class provides only utilitarian functions and there is no need to have instances of it.
     */
    private function __construct() {}

    /**
     * Check if payment method's code is one of Payment Services PayPal payment method
     *
     * @param string $paymentMethodCode
     * @return bool
     */
    public static function isPaymentServicesPayPalPaymentMethod(string $paymentMethodCode): bool
    {
        return str_starts_with($paymentMethodCode, Config::PAYMENTS_SERVICES_PREFIX);
    }
}

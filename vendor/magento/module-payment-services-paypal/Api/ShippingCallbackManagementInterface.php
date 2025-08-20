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

use Magento\Framework\Exception\LocalizedException;

/**
 * Interface for handling PayPal shipping callbacks
 */
interface ShippingCallbackManagementInterface
{
    /**
     * Handle PayPal shipping callback
     *
     * @param string $cartId
     * @param string $sessionId
     * @param string $requestBody
     * @return array
     * @throws LocalizedException
     */
    public function execute(
        string $cartId,
        string $sessionId,
        string $requestBody
    ): array;
}

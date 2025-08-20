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

interface CompleteOrderInterface
{
    /**
     * Rest API endpoint to place an order
     *
     * @param string $orderId
     * @return string
     */
    public function execute(string $orderId): string;

    /**
     * GraphQL endpoint to update the quote and place an order
     *
     * @param int $cartId
     * @param string $orderId
     * @return int
     */
    public function syncAndPlaceOrder(int $cartId, string $orderId): int;
}

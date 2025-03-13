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

namespace Magento\PaymentServicesPaypal\Model;

class PaypalMerchantData implements PaypalMerchantInterface
{
    /**
     * @param ?string $id
     * @param ?string $status
     */
    public function __construct(
        private ?string $id,
        private ?string $status
    ) {
    }

    /**
     * Get Id
     *
     * @return ?string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Get Status
     *
     * @return ?string
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }
}

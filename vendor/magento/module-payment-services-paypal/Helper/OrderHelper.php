<?php

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2023 Adobe
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
use Magento\PaymentServicesPaypal\Model\HostedFieldsConfigProvider;
use Magento\Quote\Model\Quote;

class OrderHelper
{
    /**
     * Payment sources that require L2/L3 data
     */
    private const L2_L3_PAYMENT_SOURCES = [
        HostedFieldsConfigProvider::CC_SOURCE,
        HostedFieldsConfigProvider::VAULT_SOURCE
    ];

    /**
     * @var L2DataProvider
     */
    private L2DataProvider $l2DataProvider;

    /**
     * @var L3DataProvider
     */
    private L3DataProvider $l3DataProvider;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param L2DataProvider $l2DataProvider
     * @param L3DataProvider $l3DataProvider
     * @param Config $config
     */
    public function __construct(
        L2DataProvider $l2DataProvider,
        L3DataProvider $l3DataProvider,
        Config $config
    ) {
        $this->l2DataProvider = $l2DataProvider;
        $this->l3DataProvider = $l3DataProvider;
        $this->config = $config;
    }

    /**
     * Format the amount with two decimal places
     *
     * @param float $amount
     * @return string
     */
    public function formatAmount(float $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    /**
     * Get L2 data for the given cart
     *
     * Only certain payment sources support L2 data
     *
     * @param Quote $quote
     * @param string $paymentSource
     * @return array
     */
    public function getL2Data(Quote $quote, string $paymentSource): array
    {
        return $this->isL2L3DataApplicable($paymentSource)
            ? $this->l2DataProvider->getL2Data($quote)
            : [];
    }

    /**
     * Get L3 data for the given cart
     *
     * Only certain payment sources support L3 data
     *
     * @param Quote $quote
     * @param string $paymentSource
     * @return array
     */
    public function getL3Data(Quote $quote, string $paymentSource): array
    {
        return $this->isL2L3DataApplicable($paymentSource)
            ? $this->l3DataProvider->getL3Data($quote)
            : [];
    }

    /**
     * Reserve and get the order increment ID
     *
     * @param Quote $quote
     * @return string
     */
    public function reserveAndGetOrderIncrementId(Quote $quote): string
    {
        $quote->reserveOrderId();
        return $quote->getReservedOrderId();
    }

    /**
     * Check if L2/L3 data are applicable to the order
     *
     * @param string $paymentSource
     * @return bool
     */
    private function isL2L3DataApplicable(string $paymentSource): bool
    {
        return $this->config->isL2L3SendDataEnabled() && $this->isSupportedPaymentSource($paymentSource);
    }

    /**
     * Check if the payment source supports L2/L3 data
     *
     * @param string $paymentSource
     * @return bool
     */
    private function isSupportedPaymentSource(string $paymentSource): bool
    {
        return in_array($paymentSource, self::L2_L3_PAYMENT_SOURCES);
    }
}

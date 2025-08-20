<?php
/*************************************************************************
 * ADOBE CONFIDENTIAL
 * ___________________
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
 **************************************************************************/
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model\Api\Data;

use Magento\PaymentServicesPaypal\Api\Data\PaymentConfigFastlaneInterface;

class PaymentConfigFastlane extends PaymentConfigItem implements PaymentConfigFastlaneInterface
{
    /**
     * @inheritdoc
     */
    public function getPaymentSource(): string
    {
        return $this->getData(self::PAYMENT_SOURCE);
    }
    /**
     * @inheritdoc
     */
    public function setPaymentSource(string $paymentSource): PaymentConfigFastlane
    {
        return $this->setData(self::PAYMENT_SOURCE, $paymentSource);
    }
}

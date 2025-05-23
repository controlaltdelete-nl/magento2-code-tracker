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

namespace Magento\PaymentServicesBase\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ScopeInterface;

class MBAScopingLevel implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => ScopeInterface::SCOPE_WEBSITE, 'label' => __('Website')],
            ['value' => ScopeInterface::SCOPE_STORE, 'label' => __('Storeview')],
        ];
    }
}

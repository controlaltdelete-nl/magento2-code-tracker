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

namespace Magento\PaymentServicesPaypal\Model\Adminhtml\Source;

use Magento\Framework\DataObject;
use Magento\Framework\Phrase;

class PaypalMerchantStatusInformation extends DataObject
{
    private const MESSAGE = 'message';
    private const STYLE = 'style';
    private const STATUS = 'status';

    /**
     * @param ?Phrase $message
     * @param string $style
     * @param Phrase $status
     */
    public function __construct(?Phrase $message, string $style, Phrase $status)
    {
        parent::__construct([self::MESSAGE => $message, self::STYLE => $style, self::STATUS => $status]);
    }

    /**
     * Get Message
     *
     * @return ?string
     */
    public function getMessage(): ?string
    {
        return (string) $this->getData(self::MESSAGE);
    }

    /**
     * Get Style
     *
     * @return string
     */
    public function getStyle(): string
    {
        return (string) $this->getData(self::STYLE);
    }

    /**
     * Get Status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }
}

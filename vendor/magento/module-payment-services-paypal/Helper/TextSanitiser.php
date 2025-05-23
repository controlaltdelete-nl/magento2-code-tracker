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

namespace Magento\PaymentServicesPaypal\Helper;

use Psr\Log\LoggerInterface;

class TextSanitiser
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Removes everything except letters, numbers, spaces, and punctuation.
     *
     * Takes into account Unicode characters to support text in multiple languages.
     *
     * @param string $input
     * @return string
     */
    public function textOnly(string $input): string
    {
        $output = preg_replace('/[^\p{L}\p{N}\s.,!_-]/u', '', $input);

        if ($output === null && preg_last_error() !== PREG_NO_ERROR) {
            $this->logger->error('Failed to sanitise text. Error: ' . preg_last_error_msg());
            return '';
        }

        return $output;
    }
}

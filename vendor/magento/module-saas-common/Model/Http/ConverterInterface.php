<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Http;

/**
 * Represents converter interface for http request and response body.
 *
 * @api
 */
interface ConverterInterface
{
    /**
     * Convert from body
     *
     * @param string $body
     * @return array
     */
    public function fromBody($body) : array;

    /**
     * Convert to body
     *
     * @param array $data
     * @return string
     */
    public function toBody(array $data) : string;

    /**
     * Get content-type header
     *
     * @return string
     */
    public function getContentTypeHeader() : string;

    /**
     * Get media-type header
     *
     * @return string
     */
    public function getContentMediaType() : string;

    /**
     * Get content encoding header
     *
     * @return string
     */
    public function getContentEncoding() : ?string;
}

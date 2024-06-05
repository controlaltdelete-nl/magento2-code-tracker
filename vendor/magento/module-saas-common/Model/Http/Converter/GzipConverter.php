<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Http\Converter;

use Magento\SaaSCommon\Model\Http\ConverterInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Represents Gzip converter for http request and response body.
 */
class GzipConverter implements ConverterInterface
{
    /**
     * Media-Type corresponding to this converter.
     */
    public const CONTENT_MEDIA_TYPE = 'application/json';

    /**
     * Content encoding type
     */
    public const CONTENT_ENCODING = 'gzip';

    private Json $serializer;

    /**
     * @param Json $serializer
     */
    public function __construct(Json $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @inheritdoc
     */
    public function fromBody($body): array
    {
        $decodedBody = $this->serializer->unserialize($body);
        return $decodedBody ?? [$body];
    }

    /**
     * @inheritdoc
     */
    public function toBody(array $data): string
    {
        if (!\extension_loaded('zlib')) {
            throw new \RuntimeException('PHP extension zlib is required.');
        }
        //phpcs:ignore Magento2.Functions.DiscouragedFunction
        return \gzencode($this->serializer->serialize($data));
    }

    /**
     * @inheritdoc
     */
    public function getContentTypeHeader(): string
    {
        return sprintf('Content-Type: %s', self::CONTENT_MEDIA_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function getContentMediaType(): string
    {
        return self::CONTENT_MEDIA_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function getContentEncoding(): string
    {
        return self::CONTENT_ENCODING;
    }
}

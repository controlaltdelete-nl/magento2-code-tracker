<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Metadata;

use Magento\Framework\Serialize\SerializerInterface;

/**
 * Provides metadata header in JSON format
 * Example:
 * service-connector-metadata: {"commerceEdition":"B2B","commerceVersion":"2.4.6"}
 */
class RequestMetadataHeaderProvider
{
    private const METADATA_HEADER_NAME = 'service-connector-metadata';

    /**
     * @var MetadataPool
     */
    private MetadataPool $metadataPool;

    /**
     * @var null|string
     */
    private ?string $headerValue = null;

    private SerializerInterface $serializer;

    /**
     * @param MetadataPool $metadataPool
     * @param SerializerInterface $serializer
     */
    public function __construct(
        MetadataPool $metadataPool,
        SerializerInterface $serializer
    ) {
        $this->metadataPool = $metadataPool;
        $this->serializer = $serializer;
    }

    /**
     * Get Header value
     *
     * @return string
     */
    public function getValue(): string
    {
        if ($this->headerValue === null) {
            $metadata = [];

            foreach ($this->metadataPool->getAll() as $metadataObject) {
                $metadata[] = $metadataObject->get();
            }

            $this->headerValue = $metadata ? $this->serializer->serialize(array_merge(...$metadata)) : '';
        }

        return $this->headerValue;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return self::METADATA_HEADER_NAME;
    }
}

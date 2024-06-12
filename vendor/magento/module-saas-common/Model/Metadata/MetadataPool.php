<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Metadata;

use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Phrase;

/**
 * Pool of metadata objects
 */
class MetadataPool
{
    /**
     * @var RequestMetadataInterface[]
     */
    private array $metadata = [];

    /**
     * @param RequestMetadataInterface[] $metadata
     * @throws InvalidArgumentException
     */
    public function __construct(array $metadata)
    {
        foreach ($metadata as $metadataObject) {
            if (!$metadataObject instanceof RequestMetadataInterface) {
                throw new InvalidArgumentException(
                    new Phrase(
                        'Instance of "%1" is expected, got "%2" instead.',
                        [
                            RequestMetadataInterface::class,
                            get_class($metadataObject)
                        ]
                    )
                );
            }
        }
        $this->metadata = $metadata;
    }

    /**
     * Return a list of registered metadata objects.
     *
     * @return RequestMetadataInterface[]
     */
    public function getAll(): array
    {
        return $this->metadata;
    }
}

<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Metadata;

use Magento\Framework\App\ProductMetadataInterface;

/**
 * Collects and returns commerce edition and version.
 */
class CommerceEdition implements RequestMetadataInterface
{
    private const COMMUNITY_EDITION = 'ce';
    private const COMMERCE_EDITION = 'ee';
    private const B2B_EDITION = 'b2b';
    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $commerceMetadata;

    /**
     * @param ProductMetadataInterface $commerceMetadata
     */
    public function __construct(ProductMetadataInterface $commerceMetadata)
    {
        $this->commerceMetadata = $commerceMetadata;
    }

    /**
     * Collects and returns commerce edition and version.
     *
     * @return array
     */
    public function get(): array
    {
        $commerceEdition = self::COMMERCE_EDITION;

        switch ($this->commerceMetadata->getEdition()) {
            case 'Community':
                $commerceEdition = self::COMMUNITY_EDITION;
                break;
            case 'B2B':
                $commerceEdition = self::B2B_EDITION;
                break;
        }

        return [
            'commerceEdition' => $commerceEdition,
            'commerceVersion' => $this->commerceMetadata->getVersion()
        ];
    }
}

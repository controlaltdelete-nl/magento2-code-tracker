<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Metadata;

use Magento\Framework\Module\PackageInfo;

/**
 * Get Magento_SaaSCommon module version
 */
class ClientVersion implements RequestMetadataInterface
{
    /**
     * @var PackageInfo
     */
    private PackageInfo $packageInfo;

    /**
     * @param PackageInfo $packageInfo
     */
    public function __construct(PackageInfo $packageInfo)
    {
        $this->packageInfo = $packageInfo;
    }

    /**
     * Collects and returns version of the Magento_SaaSCommon module.
     *
     * @return array
     */
    public function get(): array
    {
        return [
            'saasExporterVersion' => $this->packageInfo->getVersion('Magento_SaaSCommon'),
        ];
    }
}

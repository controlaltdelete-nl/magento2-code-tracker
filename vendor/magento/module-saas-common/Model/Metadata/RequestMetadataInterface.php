<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Metadata;

/**
 * Describes request metadata
 * Returns array of key values pairs.
 * For example:
 * [
 *    'commerceEdition' => 'B2C',
 *    'commerceVersion => '2.4.6'
 * ]
 *
 */
interface RequestMetadataInterface
{
    /**
     * Get data
     *
     * @return array
     */
    public function get(): array;
}

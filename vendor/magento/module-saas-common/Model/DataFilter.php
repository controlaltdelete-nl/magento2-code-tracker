<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model;

/**
 * Filter payload data before send it to the REST endpoint
 */
class DataFilter
{
    /**
     * list of fields in feed which should be excluded
     * @var array
     */
    private $reservedFields;

    /**
     * @param array $reservedFields
     */
    public function __construct(array $reservedFields = [])
    {
        $this->reservedFields = $reservedFields;
    }

    /**
     * @param $feedName
     * @param array $feeds
     * @return array
     */
    public function filter($feedName, array $feeds) : array
    {
        if (!empty($this->reservedFields[$feedName])) {
            foreach ($feeds as &$feedItem) {
                foreach ($feedItem as $field => $value) {
                    if (in_array($field, $this->reservedFields[$feedName], true)) {
                        unset($feedItem[$field]);
                    }
                }
            }
        }

        return $feeds;
    }
}

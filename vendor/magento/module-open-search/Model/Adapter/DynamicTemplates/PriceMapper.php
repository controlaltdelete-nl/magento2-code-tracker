<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\OpenSearch\Model\Adapter\DynamicTemplates;

/**
 * @inheritDoc
 */
class PriceMapper implements MapperInterface
{
    /**
     * @inheritDoc
     */
    public function processTemplates(array $templates): array
    {
        $templates[] = [
            'price_mapping' => [
                'match' => 'price_*',
                'match_mapping_type' => 'string',
                'mapping' => [
                    'type' => 'double',
                    'store' => true,
                ],
            ],
        ];

        return $templates;
    }
}

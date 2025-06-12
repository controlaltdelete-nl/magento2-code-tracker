<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

return [
    // the following modules must be disabled when Live Search is used
    // so core modules must not be dependent on them
    'Magento\LiveSearch' => [
        'Magento\Elasticsearch',
        'Magento\Elasticsearch6',
        'Magento\Elasticsearch7',
        'Magento\OpenSearch'
    ],
];

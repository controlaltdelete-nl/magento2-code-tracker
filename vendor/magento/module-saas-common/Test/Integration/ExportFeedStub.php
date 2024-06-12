<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Test\Integration;

use Magento\DataExporter\Model\ExportFeedInterface;
use Magento\DataExporter\Model\FeedExportStatus;
use Magento\DataExporter\Model\Indexer\FeedIndexMetadata;
use Magento\DataExporter\Status\ExportStatusCode;
use Magento\TestFramework\Helper\Bootstrap;

class ExportFeedStub implements ExportFeedInterface
{
    /**
     * Export data
     *
     * @param array $data
     * @param FeedIndexMetadata $metadata
     * @return FeedExportStatus
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function export(array $data, FeedIndexMetadata $metadata): FeedExportStatus
    {

        $statusCode = Bootstrap::getObjectManager()->create(ExportStatusCode::class, ['statusCode' => 200]);
        return Bootstrap::getObjectManager()->create(
            FeedExportStatus::class,
            [
                'status' => $statusCode,
                'reasonPhrase' => '',
                'failedItems' => []
            ]
        );
    }
}

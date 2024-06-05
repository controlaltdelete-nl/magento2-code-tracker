<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model;

use Magento\DataExporter\Model\ExportFeedInterface;
use Magento\DataExporter\Model\FeedExportStatus;
use Magento\DataExporter\Model\Indexer\FeedIndexMetadata;
use Magento\SaaSCommon\Model\Http\Command\SubmitFeed;

class ExportFeed implements ExportFeedInterface
{
    /**
     * @var SubmitFeed
     */
    private SubmitFeed $submitFeed;

    /**
     * @param SubmitFeed $submitFeed
     */
    public function __construct(
        SubmitFeed $submitFeed
    ) {
        $this->submitFeed = $submitFeed;
    }

    /**
     * {@inheirtDoc}
     *
     * @param array $data
     * @param FeedIndexMetadata $metadata
     * @return FeedExportStatus
     */
    public function export(array $data, FeedIndexMetadata $metadata): FeedExportStatus
    {
        return $this->submitFeed->execute($metadata->getFeedName(), $data);
    }
}

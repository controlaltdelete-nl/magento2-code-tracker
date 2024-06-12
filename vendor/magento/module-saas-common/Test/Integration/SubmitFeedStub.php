<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Test\Integration;

use Magento\DataExporter\Model\FeedExportStatus;
use Magento\DataExporter\Model\FeedExportStatusBuilder;
use Magento\SaaSCommon\Model\Http\Command\SubmitFeed;
use Magento\TestFramework\Helper\Bootstrap;

class SubmitFeedStub extends SubmitFeed
{
    /**
     * Export data
     *
     * @param string $feedName
     * @param array $data
     * @param int|null $timeout
     * @return FeedExportStatus
     */
    public function execute(string $feedName, array $data, int $timeout = null) : FeedExportStatus
    {

        $feedExportStatusBuilder = Bootstrap::getObjectManager()->create(FeedExportStatusBuilder::class);
        return $feedExportStatusBuilder->build(
            200,
            'OK',
            []
        );
    }
}

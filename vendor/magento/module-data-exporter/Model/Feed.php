<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */
declare(strict_types=1);

namespace Magento\DataExporter\Model;

use Magento\DataExporter\Model\Indexer\FeedIndexMetadata;
use Magento\DataExporter\Model\Logging\CommerceDataExportLoggerInterface;
use Magento\DataExporter\Model\Query\FeedQuery;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class responsible for providing feed data
 * @deprecated use \Magento\DataExporter\Model\Batch\Feed\Generator to prepare feeds collection
 * @see \Magento\DataExporter\Model\Batch\Feed\Generator
 */
class Feed implements FeedInterface
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @var SerializerInterface
     */
    protected SerializerInterface $serializer;

    /**
     * @var FeedIndexMetadata
     */
    protected FeedIndexMetadata $feedIndexMetadata;

    /**
     * @var FeedQuery
     */
    private FeedQuery $feedQuery;

    /**
     * @var CommerceDataExportLoggerInterface
     */
    private CommerceDataExportLoggerInterface $logger;

    /**
     * @param ResourceConnection $resourceConnection
     * @param SerializerInterface $serializer
     * @param FeedIndexMetadata $feedIndexMetadata
     * @param FeedQuery $feedQuery
     * @param CommerceDataExportLoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        SerializerInterface $serializer,
        FeedIndexMetadata $feedIndexMetadata,
        FeedQuery $feedQuery,
        CommerceDataExportLoggerInterface $logger,
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->serializer = $serializer;
        $this->feedIndexMetadata = $feedIndexMetadata;
        $this->feedQuery = $feedQuery;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getFeedSince(string $timestamp, array $ignoredExportStatus = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $limit = $connection->fetchOne(
            $this->feedQuery->getLimitSelect(
                $this->feedIndexMetadata,
                $timestamp,
                $this->feedIndexMetadata->getBatchSize(),
                $ignoredExportStatus
            )
        );
        if ($ignoredExportStatus !== null) {
            $this->logger->warning(__METHOD__ . ' is deprecated. $ignoredExportStatus parameter is ignored');
        }
        return $this->fetchData(
            $this->feedQuery->getDataSelect(
                $this->feedIndexMetadata,
                $timestamp,
                !$limit ? null : $limit,
                $ignoredExportStatus
            )
        );
    }

    /**
     * Fetch data from prepared select statement
     *
     * @param string|\Magento\Framework\DB\Select $select
     * @return array
     * @throws \Zend_Db_Statement_Exception
     */
    private function fetchData($select): array
    {
        $connection = $this->resourceConnection->getConnection();
        $recentTimestamp = null;

        $cursor = $connection->query($select);
        $output = [];
        while ($row = $cursor->fetch()) {
            $dataRow = $this->serializer->unserialize($row['feed_data']);
            $rowModifiedAt = $row['modifiedAt'];

            if (isset($row['deleted'])) {
                $dataRow['deleted'] = (bool)$row['deleted'];
            }
            $output[] = $dataRow;
            if ($recentTimestamp === null || $recentTimestamp < $rowModifiedAt) {
                $recentTimestamp = $rowModifiedAt;
            }
        }
        return [
            'recentTimestamp' => $recentTimestamp,
            'feed' => $output,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getFeedMetadata(): FeedIndexMetadata
    {
        return $this->feedIndexMetadata;
    }
}

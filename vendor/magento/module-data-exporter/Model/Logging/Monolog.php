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

namespace Magento\DataExporter\Model\Logging;

use DateTimeZone;
use Magento\DataExporter\Model\Indexer\FeedIndexMetadata;
use Monolog\DateTimeImmutable;

/**
 * Proxy class to allow to instantiate custom Logger Interface
 */
class Monolog extends \Magento\Framework\Logger\Monolog implements CommerceDataExportLoggerInterface {

    private LogRegistry $logRegistry;

    public function __construct(
        LogRegistry   $logRegistry,
        string        $name, array $handlers = [],
        array         $processors = [],
        ?DateTimeZone $timezone = null) {
        parent::__construct($name, $handlers, $processors, $timezone);
        $this->logRegistry = $logRegistry;
    }

    /**
     * @inheritDoc
     */
    public function initSyncLog(FeedIndexMetadata $metadata, string $operation, bool $logMessage = true): void
    {
        $this->logRegistry->initSyncLog($metadata, $operation);
        if ($logMessage) {
            $this->info('Initialize');
        }
    }

    /**
     * @inheritDoc
     */
    public function logProgress($processedIdsNumber = null, $syncedItems = null): void
    {
        $context = [];
        if ($processedIdsNumber) {
            $context[LogRegistry::PROCESSED_ITEMS] = $processedIdsNumber;
        }
        if ($syncedItems) {
            $context[LogRegistry::SYNCED_ITEMS] = $syncedItems;
        }

        if ($processedIdsNumber == null && $syncedItems == null) {
            $context[LogRegistry::COMPLETE_ITERATION] = true;
        }

        $message =  $this->logRegistry->prepareMessage('', $context);
        if ($message) {
            parent::addRecord(static::INFO, $message);
        }
    }

    /**
     * @inheritDoc
     */
    public function addRecord(int $level, string $message, array $context = [], DateTimeImmutable $datetime = null): bool
    {
        return parent::addRecord($level, $this->logRegistry->prepareMessage($message, []), $context, $datetime);
    }

    /**
     * @inheritDoc
     */
    public function addContext(array $context): void
    {
        $this->logRegistry->addContext($context);
    }

    /**
     * @inheritDoc
     */
    public function complete(): void
    {
        $this->info('Complete');
    }
}
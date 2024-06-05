<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Http\Command;

use GuzzleHttp\Client;
use Laminas\Http\Request;
use Magento\DataExporter\Model\FeedExportStatus;
use Magento\DataExporter\Model\Indexer\Config as IndexerConfig;
use Magento\DataExporter\Status\ExportStatusCodeProvider;
use Magento\Framework\App\ObjectManager;
use Magento\SaaSCommon\Console\ProgressBarManager;
use Magento\SaaSCommon\Model\DataFilter;
use Magento\SaaSCommon\Model\Exception\UnableSendData;
use Magento\SaaSCommon\Model\Http\ResponseParser;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\SaaSCommon\Model\Http\Converter\Factory;
use Magento\SaaSCommon\Model\Http\ConverterInterface;
use Magento\SaaSCommon\Model\Metadata\RequestMetadataHeaderProvider;
use Magento\ServicesConnector\Api\ClientResolverInterface;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Magento\SaaSCommon\Model\Logging\SaaSExportLoggerInterface as LoggerInterface;
use Magento\DataExporter\Model\FeedExportStatusBuilder;

/**
 * Class responsible for call execution to SaaS Service
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SubmitFeed
{
    /**
     * Config paths
     */
    private const ROUTE_CONFIG_PATH = 'commerce_data_export/routes/';
    private const ENVIRONMENT_CONFIG_PATH = 'magento_saas/environment';

    /**
     * Extension name for Services Connector
     */
    private const EXTENSION_NAME = 'Magento_DataExporter';

    /**
     * @var ClientResolverInterface
     */
    private $clientResolver;

    /**
     * @var ConverterInterface
     */
    private $converter;

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var ServicesConfigInterface
     */
    private $servicesConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $extendedLog;

    /**
     * @var string[]
     */
    private $headers;

    /**
     * @var DataFilter
     */
    private $dataFilter;

    private ResponseParser $responseParser;

    private FeedExportStatusBuilder $feedExportStatusBuilder;

    private RequestMetadataHeaderProvider $requestMetadataHeaderProvider;

    private IndexerConfig $indexerConfig;

    private ProgressBarManager $progressBarManager;

    /**
     * @param ClientResolverInterface $clientResolver
     * @param Factory $converterFactory
     * @param ScopeConfigInterface $config
     * @param ServicesConfigInterface $servicesConfig
     * @param LoggerInterface $logger
     * @param DataFilter $dataFilter
     * @param ResponseParser $responseParser
     * @param FeedExportStatusBuilder $feedExportStatusBuilder
     * @param RequestMetadataHeaderProvider $requestMetadataHeaderProvider
     * @param IndexerConfig $indexerConfig
     * @param ProgressBarManager $progressBarManager
     * @param bool $extendedLog
     * @param string[] $headers
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ClientResolverInterface $clientResolver,
        Factory $converterFactory,
        ScopeConfigInterface $config,
        ServicesConfigInterface $servicesConfig,
        LoggerInterface $logger,
        DataFilter $dataFilter,
        ResponseParser $responseParser,
        FeedExportStatusBuilder $feedExportStatusBuilder,
        RequestMetadataHeaderProvider $requestMetadataHeaderProvider,
        ?IndexerConfig $indexerConfig = null,
        ?ProgressBarManager $progressBarManager = null,
        bool $extendedLog = false,
        array $headers = []
    ) {
        $this->clientResolver = $clientResolver;
        $this->converter = $converterFactory->create();
        $this->config = $config;
        $this->servicesConfig = $servicesConfig;
        $this->logger = $logger;
        $this->extendedLog = $extendedLog;
        $this->headers = $headers;
        $this->dataFilter = $dataFilter;
        $this->responseParser = $responseParser;
        $this->feedExportStatusBuilder = $feedExportStatusBuilder;
        $this->requestMetadataHeaderProvider = $requestMetadataHeaderProvider;
        $this->indexerConfig = $indexerConfig
            ?? ObjectManager::getInstance()->get(IndexerConfig::class);
        $this->progressBarManager = $progressBarManager
            ?? ObjectManager::getInstance()->get(ProgressBarManager::class);
    }

    /**
     * Build URL to SaaS Service
     *
     * @param string $feedName
     * @param ?string $environmentId
     * @return string
     * @throws UnableSendData
     */
    private function getUrl(string $feedName, ?string $environmentId) : string
    {
        $route =  $this->getRoute($feedName);

        if (empty($route) || empty($environmentId)) {
            throw new UnableSendData('Cannot build feed url');
        }

        return '/' . $route . '/' . $environmentId;
    }

    /**
     * Execute call to SaaS Service
     * Returns status of operation:
     * - true: feed submitted successfully
     * - false: feed submitted unsuccessfully. Need to retry feed submission
     *
     * @param string $feedName
     * @param array $data
     * @param int|null $timeout
     * @return FeedExportStatus
     */
    public function execute(string $feedName, array $data, int $timeout = null) : FeedExportStatus
    {
        if (true === $this->indexerConfig->isDryRun()) {
            $this->logFeedData($feedName, $data);
            $this->progressBarManager->updateExportInfo(count($data), 0);
            return $this->feedExportStatusBuilder->build(
                ExportStatusCodeProvider::FEED_SUBMIT_SKIPPED,
                'Feed submission is skipped as executed in the "dummy" mode.'
            );
        }
        $environmentId = $this->servicesConfig->getEnvironmentId();
        try {
            $client = $this->clientResolver->createHttpClient(
                self::EXTENSION_NAME,
                $this->config->getValue(self::ENVIRONMENT_CONFIG_PATH)
            );

            $headers = $this->getHeaders();
            $data = $this->dataFilter->filter($feedName, $data);
            $this->logFeedData($feedName, $data);
            $body = $this->converter->toBody($data);
            $options = [
                'headers' => $headers,
                'body' => $body
            ];

            if (null !== $timeout) {
                $options['timeout'] = $timeout;
            }
            if ($this->servicesConfig->isApiKeySet()) {
                $response = $client->request(Request::METHOD_POST, $this->getUrl($feedName, $environmentId), $options);
                $failedItems = $this->responseParser->parse($response);
                $exportStatus = $this->feedExportStatusBuilder->build(
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    $failedItems
                );
                if (!$exportStatus->getStatus()->isSuccess()) {
                    $log = $this->prepareLog($client, $exportStatus, $feedName, $data, $environmentId);
                    $this->logger->error(
                        'Export error. API request was not successful.',
                        $log
                    );
                }
                $this->progressBarManager->updateExportInfo(
                    count($data),
                    count($failedItems),
                    $exportStatus->getStatus()->isSuccess()
                );
            } else {
                throw new UnableSendData('API Keys Validation Failed');
            }
        } catch (\Throwable $exception) {
            $exportStatus = $this->feedExportStatusBuilder->build(
                ExportStatusCodeProvider::APPLICATION_ERROR,
                $exception->getMessage()
            );
            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                    'environment_id' => $environmentId,
                    'route' => $this->getRoute($feedName),
                    'feed' => $feedName
                ]
            );
        }

        return $exportStatus;
    }

    /**
     * Prepare log formatting.
     *
     * @param Client $client
     * @param FeedExportStatus $feedExportStatus
     * @param string $feedName
     * @param array $payload
     * @param string|null $environmentId
     * @return array
     * @throws UnableSendData
     */
    private function prepareLog(
        Client $client,
        FeedExportStatus $feedExportStatus,
        string $feedName,
        array $payload,
        ?string $environmentId
    ): array {
        $clientConfig = $client->getConfig();

        $log = [
            'environment_id' => $environmentId,
            'status_code' => $feedExportStatus->getStatus()->getValue(),
            'feed' => $feedName,
            'reason' => $feedExportStatus->getReasonPhrase(),
            'route' => $this->getRoute($feedName),
            'base_uri' => $clientConfig['base_uri']
                ? $clientConfig['base_uri']->__toString() : 'base uri wasn\'t set',
            'failedItems' => $feedExportStatus->getFailedItems()
        ];

        if (true === $this->extendedLog) {
            $log['headers'] = $clientConfig['headers'] ?? 'no headers';
            $log['payload'] = $payload;
        }
        return $log;
    }

    /**
     * Create a list of headers for the feed submit request.
     *
     * @return array
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => $this->converter->getContentMediaType(),
            $this->requestMetadataHeaderProvider->getName() => $this->requestMetadataHeaderProvider->getValue()
        ];

        if (null !== $this->converter->getContentEncoding()) {
            $headers['Content-Encoding'] = $this->converter->getContentEncoding();
        }

        if (empty($this->headers)) {
            return $headers;
        }

        foreach ($this->headers as $headerName => $headerValue) {
            if (!empty($headerValue)) {
                $headers[$headerName] = $headerValue;
            }
        }

        return $headers;
    }

    /**
     * Get route from config for given feed name
     *
     * @param string $feedName
     * @return ?string
     */
    private function getRoute(string $feedName): ?string
    {
        return $this->config->getValue(self::ROUTE_CONFIG_PATH . $feedName);
    }

    /**
     * Save feed data to log
     *
     * @param string $feedName
     * @param array $data
     * @return void
     */
    private function logFeedData(string $feedName, array $data): void
    {
        if ($this->extendedLog) {
            $this->logger->info(json_encode(["feed" => $feedName, "data" => $data], JSON_PRETTY_PRINT));
        }
    }
}

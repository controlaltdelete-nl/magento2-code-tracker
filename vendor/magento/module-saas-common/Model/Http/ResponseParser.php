<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Http;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\SaaSCommon\Model\Logging\SaaSExportLoggerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Parse Feed Service API response
 */
class ResponseParser
{
    private SerializerInterface $serializer;
    private SaaSExportLoggerInterface $logger;

    /**
     * @param SerializerInterface $serializer
     * @param SaaSExportLoggerInterface $logger
     */
    public function __construct(SerializerInterface $serializer, SaaSExportLoggerInterface $logger)
    {
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Parse data
     *
     * @param ResponseInterface $response
     * @return array
     */
    public function parse(ResponseInterface $response): array
    {
        $failedItems = [];
        try {
            $responseText = $response->getBody()->getContents();
            $json = $this->serializer->unserialize($responseText);
            if (isset($json['invalidFeedItems'])) {
                foreach ($json['invalidFeedItems'] as $item) {
                    $parsedItem = $this->parseItem($item);
                    if (!$parsedItem) {
                        return  [];
                    }
                    [$index, $field, $message] = $parsedItem;
                    $failedItems[$index] = [
                        'message' => $message,
                        'field' => $field
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Cannot parse response. API request was not successful.',
                [
                    'response' => $responseText ?? 'read error',
                    'error' => $e->getMessage()
                ]
            );
        }
        return $failedItems;
    }

    /**
     * Parse item
     *
     * @param array $item
     * @return ?array
     */
    private function parseItem(array $item)
    {
        //  parse {"field": "/2/updatedAt",}
        if (isset($item['field'], $item['message'])) {
            $field = $item['field'] ?? '';
            $field = explode('/', $field);
            if (count($field) >= 3) {
                return [$field[1], $field[2], $item['message']];
            } else {
                // can't determine index.
                // TODO: main method must return [] if index not determined (all items have to be resubmitted)
                return null;
            }
        }

        if (isset($item['itemIndex'], $item['code'], $item['message'])) {
            return [$item['itemIndex'], $item['code'], $item['message']];
        }

        return null;
    }
}

<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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

namespace Magento\PaymentServicesBase\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Provides functionality to build HTTP scope headers 'x-scope-type' and 'x-scope-id'.
 */
class ScopeHeadersBuilder
{

    /**
     * Scope type HTTP request header. One of the following.
     *   |--------------------------------------------|
     *   | Internal Magento scope type | x-scope-type |
     *   |-----------------------------|--------------|
     *   |          'website'          |   'website'  |
     *   |           'store'           |  'storeview' |
     *   |--------------------------------------------|
     */
    public const SCOPE_TYPE = 'x-scope-type';

    /**
     * Scope id HTTP request header.
     */
    public const SCOPE_ID = 'x-scope-id';

    /**
     * x-scope-type header value for website scope.
     */
    public const WEBSITE_SCOPE_TYPE = "website";

    /**
     * x-scope-type header value for storeview scope.
     */
    public const STOREVIEW_SCOPE_TYPE = "storeview";

    /**
     * Maps Adobe Commerce/Magento Open Source scope types to 'x-scope-type' header values.
     */
    private const SCOPE_TYPE_HEADER_MAP = [
        ScopeInterface::SCOPE_WEBSITE => self::WEBSITE_SCOPE_TYPE,
        ScopeInterface::SCOPE_STORE => self::STOREVIEW_SCOPE_TYPE,
    ];

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Constructs scope headers for a given store.
     *
     * @param StoreInterface|string|int $storeOrStoreId store or store id
     * @return array associative array with scope headers
     * @throws NoSuchEntityException
     */
    public function buildScopeHeaders(StoreInterface|string|int $storeOrStoreId): array
    {
        return $this->doBuildScopeHeaders($storeOrStoreId);
    }

    /**
     * Constructs scope headers for the current store.
     *
     * @return array associative array with scope headers
     * @throws NoSuchEntityException
     */
    public function buildScopeHeadersForCurrentStore(): array
    {
        return $this->doBuildScopeHeaders(null);
    }

    /**
     * Constructs scope headers for a given store.
     *
     * @param StoreInterface|string|int|null $storeOrStoreId store(id?) or null for current store
     * @return array associative array with scope headers
     * @throws NoSuchEntityException
     */
    private function doBuildScopeHeaders(StoreInterface|string|int|null $storeOrStoreId): array
    {
        $store = $this->storeManager->getStore($storeOrStoreId);
        $mbaScopingLevel = $this->config->getMultiBusinessAccountScopingLevel();
        return [
            self::SCOPE_TYPE => self::SCOPE_TYPE_HEADER_MAP[$mbaScopingLevel],
            self::SCOPE_ID => $this->resolveScopeId($store, $mbaScopingLevel)
        ];
    }

    /**
     * Returns the specified scope type id of a given store.
     *
     * @param StoreInterface $store store to extract the id of
     * @param "website"|"store" $scopeType type of id to extract
     * @return string scope id as a string
     */
    private function resolveScopeId(StoreInterface $store, string $scopeType): string
    {
        return match ($scopeType) {
            ScopeInterface::SCOPE_STORE => (string) $store->getId(),
            default => (string) $store->getWebsiteId(),
        };
    }
}

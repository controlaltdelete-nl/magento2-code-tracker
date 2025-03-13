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

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

class MerchantCacheService
{
    private const MERCHANT_SCOPES_CACHE_IDENTIFIER = 'payment_services_%s_merchant_scopes';
    private const MERCHANT_SCOPES_CACHE_TYPE = 'payment_services_merchant_scopes';
    private const CACHE_LIFETIME = 3600;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param CacheInterface $cache
     * @param Json $serializer
     */
    public function __construct(
        CacheInterface $cache,
        Json $serializer
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * Load the merchant scopes from cache for specific environment
     *
     * @param string $environment
     * @return array
     */
    public function loadScopesFromCache(string $environment): array
    {
        $merchantScopes = $this->cache->load(sprintf(self::MERCHANT_SCOPES_CACHE_IDENTIFIER, $environment));
        if ($merchantScopes) {
            return $this->serializer->unserialize($merchantScopes);
        }
        return [];
    }

    /**
     * Save the merchant scopes to cache
     *
     * @param array $scopes
     * @param string $environment
     */
    public function saveScopesToCache(array $scopes, string $environment): void
    {
        $this->cache->save(
            $this->serializer->serialize($scopes),
            sprintf(self::MERCHANT_SCOPES_CACHE_IDENTIFIER, $environment),
            [self::MERCHANT_SCOPES_CACHE_TYPE],
            self::CACHE_LIFETIME
        );
    }

    /**
     * CLean the merchant scopes from cache by tags
     */
    public function cleanScopesFromCache(): void
    {
        $this->cache->clean([self::MERCHANT_SCOPES_CACHE_TYPE]);
    }
}

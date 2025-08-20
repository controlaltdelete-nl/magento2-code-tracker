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

namespace Magento\PaymentServicesBase\Model\ServicesConnector;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\ServicesConnector\Api\KeyValidationInterface;
use Psr\Log\LoggerInterface;

class KeyValidationWithPersistentCacheDecorator implements KeyValidationInterface
{
    private const CACHE_IDENTIFIER = 'PAYMENT_SERVICES_SERVICES_CONNECTOR_KEY_VALIDITY_%s_%s';
    private const CACHE_LIFETIME = 300; // 5 minutes

    /**
     * @var KeyValidationInterface
     */
    private KeyValidationInterface $delegate;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var Json
     */
    private Json $json;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param KeyValidationInterface $delegate
     * @param CacheInterface $cache
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        KeyValidationInterface $delegate,
        CacheInterface $cache,
        Json $json,
        LoggerInterface $logger,
    ) {
        $this->delegate = $delegate;
        $this->cache = $cache;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute($extension, $environment = 'production'): bool
    {
        $cachedValue = $this->getValidationResultFromCache($extension, $environment);
        if ($cachedValue !== null) {
            return $cachedValue;
        }

        return $this->validateAndCache($extension, $environment);
    }

    /**
     * Get validation result from cache or null
     *
     * @param string $extension
     * @param string $environment
     * @return bool|null validation result as a boolean or null if there is no cached validation result
     */
    private function getValidationResultFromCache(string $extension, string $environment): ?bool
    {
        $cacheKey = $this->getCacheKey($extension, $environment);

        try {
            $cachedValue = $this->cache->load($cacheKey);
        } catch (\Exception $e) {
            $this->logger->warning(
                'Failed to load key validation result from cache',
                [
                    'extension' => $extension,
                    'environment' => $environment,
                    'error' => $e->getMessage()
                ]
            );
            return null;
        }

        if ($cachedValue === false) {
            // NOTE: Contrary to what's stated in its PHPDoc, CacheInterface::load() returns
            //   ... a string|boolean (not just a string) and returns false if the value is not
            //   ... found. See issue https://github.com/magento/magento2/issues/31450.
            return null;
        }

        return $this->json->unserialize($cachedValue);
    }

    /**
     * Validate and store result in cache
     *
     * @param string $extension
     * @param string $environment
     * @return bool
     * @throws \Magento\ServicesConnector\Exception\KeyNotFoundException
     * @throws \Magento\ServicesConnector\Exception\PrivateKeySignException
     */
    private function validateAndCache(string $extension, string $environment): bool
    {
        $isValid = $this->delegate->execute($extension, $environment);
        $cacheKey = $this->getCacheKey($extension, $environment);

        try {
            $this->cache->save(
                $this->json->serialize($isValid),
                $cacheKey,
                [],
                self::CACHE_LIFETIME
            );
        } catch (\Exception $e) {
            $this->logger->warning(
                'Failed to cache key validation result',
                [
                    'extension' => $extension,
                    'environment' => $environment,
                    'error' => $e->getMessage()
                ]
            );
        }

        return $isValid;
    }

    /**
     * Generate cache key for the validation result
     *
     * @param string $extension
     * @param string $environment
     * @return string
     */
    private function getCacheKey(string $extension, string $environment): string
    {
        return sprintf(self::CACHE_IDENTIFIER, $extension, $environment);
    }
}

<?php
/**
 * ADOBE CONFIDENTIAL
 *
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

namespace Magento\PaymentServicesBase\Model;

use Laminas\Http\Request;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Request\Http;

class MerchantService
{
    public const ADMIN_MERCHANT_URL = '/admin/merchant';
    private const SCOPES_MERCHANT_URL = '/config/scopes/merchant/%s';

    /**
     * @var ServiceClientInterface
     */
    private ServiceClientInterface $serviceClient;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var MerchantCacheService
     */
    private MerchantCacheService $cache;

    /**
     * @param Config $config
     * @param ServiceClientInterface $serviceClient
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param MerchantCacheService $cache
     */
    public function __construct(
        Config $config,
        ServiceClientInterface $serviceClient,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        MerchantCacheService $cache
    ) {
        $this->config = $config;
        $this->serviceClient = $serviceClient;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->cache = $cache;
    }

    /**
     * Validates environment, sends delete merchant request to configuration service and resets values in config
     *
     * @param string $environment
     * @return array
     */
    public function delete(string $environment): array
    {
        $response = ['is_successful' => false];

        if ($environment != 'sandbox' && $environment != 'production') {
            return $response;
        }

        $merchantId = $this->config->getMerchantId($environment);

        $response =  $this->serviceClient->request(
            ['Content-Type' => 'application/json',
                'x-mp-merchant-id' => $merchantId],
            self::ADMIN_MERCHANT_URL,
            Http::METHOD_DELETE,
            '',
            'json',
            $environment,
        );

        if (isset($response['is_successful']) && $response['is_successful']) {
            $this->updateConfigValueInStorage($environment);
        }

        return $response;
    }

    /**
     * Get a list of all scopes for a merchant
     *
     * @return array
     */
    public function getAllScopesForMerchant(): array
    {
        $environment = $this->config->getEnvironmentType();
        $merchantId = $this->config->getMerchantId($environment);

        // merchant id not empty
        if (empty($merchantId)) {
            return [];
        }

        // Check in cache first
        $scopes = $this->cache->loadScopesFromCache($environment);
        if (!empty($scopes)) {
            return $scopes;
        }

        $response =  $this->serviceClient->request(
            ['Content-Type' => 'application/json', 'x-mp-merchant-id' => $merchantId],
            sprintf(self::SCOPES_MERCHANT_URL, $merchantId),
            Http::METHOD_GET,
            '',
            'json',
            $environment,
        );

        $scopes = [];

        if (isset($response['is_successful']) && $response['is_successful'] === true
            && isset($response['mp-merchant']['merchant-scope'])) {
            $scopes = $response['mp-merchant']['merchant-scope'];
            $this->cache->saveScopesToCache($scopes, $environment);
        }

        return $scopes;
    }

    /**
     * Updates merchant ids values in commerce configuration
     *
     * @param string $environment
     * @return void
     */
    private function updateConfigValueInStorage(string $environment): void
    {
        if ($environment == 'sandbox') {
            $this->configWriter->save('payment/payment_methods/sandbox_merchant_id', '');
        } elseif ($environment == 'production') {
            $this->configWriter->save('payment/payment_methods/production_merchant_id', '');
        }
        $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }

    /**
     * Get merchant and partner information
     *
     * @param string $scopeType
     * @param int $scopeId
     * @return array
     */
    public function getMerchantAndPartnerInformation(string $scopeType, int $scopeId): array
    {
        $environment = $this->config->getEnvironmentType();
        $response = $this->serviceClient->request(
            [
                'Content-Type' => 'application/json',
                'x-scope-type' => $scopeType,
                'x-scope-id' => $scopeId
            ],
            '/admin/merchant/' . $this->config->getMerchantId($environment) . '/paypal',
            Request::METHOD_GET
        );

        if ($response['is_successful'] && $response['status'] === 200) {
            return $response;
        }

        throw new HttpException('Failed to fetch the merchant and partner information.');
    }
}

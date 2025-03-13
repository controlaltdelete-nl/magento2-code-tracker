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

namespace Magento\PaymentServicesPaypal\Test\Unit\Model;

use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaypalMerchantResolverTest extends TestCase
{
    /**
     * @var MockObject|Config $config
     */
    private Config $config;

    /**
     * @var MockObject|MerchantService $merchantService
     */
    private MerchantService $merchantService;

    /**
     * @var MockObject|StoreManager $storeManager
     */
    private StoreManager $storeManager;

    /**
     * @var PaypalMerchantResolver
     */
    private $paypalMerchantResolver;

    /**
     * Setup the test
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->merchantService = $this->createMock(MerchantService::class);
        $this->storeManager = $this->createMock(StoreManager::class);

        $this->paypalMerchantResolver = new PaypalMerchantResolver(
            $this->config,
            $this->merchantService,
            $this->storeManager
        );
    }

    /**
     * @return void
     */
    public function testGetPayPalMerchantIsNullBecauseNoScopesExist(): void
    {
        $this->config->expects($this->once())
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_WEBSITE);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->willReturn([]);

        $paypalMerchant = $this->paypalMerchantResolver->getPayPalMerchant(ScopeInterface::SCOPE_WEBSITE, 1);

        $this->assertNull($paypalMerchant->getId());
        $this->assertNull($paypalMerchant->getStatus());
    }

    /**
     * @return void
     */
    public function testGetPayPalMerchantWhenTheOnlyScopeIsGlobal(): void
    {
        $globalScope = [
            "scopeId" => 0,
            "scopeType" => "GLOBAL",
            "paypal-account" => [
                "id" => "Luke",
                "status" => "Skywalker"
            ]
        ];

        $this->config->expects($this->once())
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_WEBSITE);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->willReturn([$globalScope]);

        $paypalMerchant = $this->paypalMerchantResolver->getPayPalMerchant(ScopeInterface::SCOPE_WEBSITE, 1);

        $this->assertEquals("Luke", $paypalMerchant->getId());
        $this->assertEquals("Skywalker", $paypalMerchant->getStatus());
    }

    /**
     * @return void
     */
    public function testGetPayPalMerchantWhenThePayPalMerchantIdIsNull(): void
    {
        $scopes = [
            "scopeId" => 2,
            "scopeType" => "WEBSITE",
            "paypal-account" => [
                "id" => null,
                "status" => "STARTED"
            ]
        ];

        $this->config->expects($this->once())
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_WEBSITE);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->willReturn([$scopes]);

        $paypalMerchant = $this->paypalMerchantResolver->getPayPalMerchant(ScopeInterface::SCOPE_WEBSITE, 2);

        $this->assertNull($paypalMerchant->getId());
        $this->assertEquals("STARTED", $paypalMerchant->getStatus());
    }

    /**
     * @dataProvider paypalMerchantProviderForMBAAtWebsiteLevel
     *
     * @param string $scopeType
     * @param int $scopeId
     * @param string $expectedMerchantId
     * @param string $expectedMerchantStatus
     * @param ?int $parentId
     * @return void
     */
    public function testGetPayPalMerchantWithMbaScopingSetAsWebsite(
        string $scopeType,
        int    $scopeId,
        string $expectedMerchantId,
        string $expectedMerchantStatus,
        ?int   $parentId
    ): void {
        $scopes = $this->getScopes();

        $this->config->expects($this->once())
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_WEBSITE);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->willReturn($scopes);

        if ($scopeType === ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE) {
            $store = $this->createMock(Store::class);

            $store->expects($this->once())
                ->method('getWebsiteId')
                ->willReturn($parentId);

            $this->storeManager->expects($this->once())
                ->method('getStore')
                ->with($scopeId)
                ->willReturn($store);
        } else {
            $this->storeManager->expects($this->never())
                ->method('getStore');
        }

        $paypalMerchant = $this->paypalMerchantResolver->getPayPalMerchant($scopeType, $scopeId);

        $this->assertEquals($expectedMerchantId, $paypalMerchant->getId());
        $this->assertEquals($expectedMerchantStatus, $paypalMerchant->getStatus());
    }

    /**
     * Data provider for testGetPayPalMerchantWithMbaScopingSetAsWebsite.
     *
     * @return array
     * [
     *  scopeType
     *  scopeId
     *  paypalMerchantId
     *  paypalMerchantStatus
     *  parentId (in case it's a storeview, we need to get its website parent)
     * ]
     */
    public function paypalMerchantProviderForMBAAtWebsiteLevel(): array
    {
        return [
            'Global Scope' => [PaypalMerchantResolver::GLOBAL_SCOPE, 0, 'paypalsellerid_global', 'COMPLETED', null],
            'Website Scope' => [ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE, 1, 'paypalsellerid_website1', 'STARTED', null],
            'Store View Scope with config at website level' => [ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE, 2, 'paypalsellerid_website1', 'STARTED', 1], // this store view as the website id 1 as parent
            'Store View Scope without config at website level' => [ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE, 3, 'paypalsellerid_global', 'COMPLETED', 2], // this store view as the website id 2 as parent
            'Non-existing Scope' => [ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE, 999, 'paypalsellerid_global', 'COMPLETED', null],
        ];
    }

    /**
     * @dataProvider paypalMerchantProviderForMBAAtStoreViewLevel
     *
     * @param string $scopeType
     * @param int $scopeId
     * @param string $expectedMerchantId
     * @param string $expectedMerchantStatus
     * @return void
     */
    public function testGetPayPalMerchantWithMbaScopingSetAsStoreViewLevel(
        string $scopeType,
        int $scopeId,
        string $expectedMerchantId,
        ?string $expectedMerchantStatus
    ): void {
        $scopes = $this->getScopes();

        $this->config->expects($this->once())
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_STORE);

        $this->merchantService->expects($this->once())
            ->method('getAllScopesForMerchant')
            ->willReturn($scopes);

        $this->storeManager->expects($this->never())
                ->method('getStore');

        $paypalMerchant = $this->paypalMerchantResolver->getPayPalMerchant($scopeType, $scopeId);

        $this->assertEquals($expectedMerchantId, $paypalMerchant->getId());
        $this->assertEquals($expectedMerchantStatus, $paypalMerchant->getStatus());
    }

    /**
     * Data provider for testGetPayPalMerchantWithMbaScopingSetAsStoreViewLevel
     *
     * @return array
     * [
     *  scopeType
     *  scopeId
     *  paypalMerchantId
     *  paypalMerchantStatus
     * ]
     */
    public function paypalMerchantProviderForMBAAtStoreViewLevel(): array
    {
        return [
            'Global Scope' => [PaypalMerchantResolver::GLOBAL_SCOPE, 0, 'paypalsellerid_global', 'COMPLETED'],
            'Website Scope' => [ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE, 1, 'paypalsellerid_global', 'COMPLETED'],
            'Store View Scope with config' => [ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE, 2, 'paypalsellerid_storeview2', null],
            'Store View Scope without config' => [ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE, 3, 'paypalsellerid_global', 'COMPLETED'],
            'Non-existing Scope' => [ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE, 999, 'paypalsellerid_global', 'COMPLETED'],
        ];
    }

    /**
     * Get a list of scopes
     *
     * @return array[]
     */
    private function getScopes(): array
    {
        return [
            0 => [
                "scopeId" => 0,
                "scopeType" => "GLOBAL",
                "paypal-account" => [
                    "id" => "paypalsellerid_global",
                    "status" => "COMPLETED"
                ]
            ],
            1 => [
                "scopeId" => 1,
                "scopeType" => "WEBSITE",
                "paypal-account" => [
                    "id" => "paypalsellerid_website1",
                    "status" => "STARTED"
                ]
            ],
            2 => [
                "scopeId" => 2,
                "scopeType" => "STOREVIEW",
                "paypal-account" => [
                    "id" => "paypalsellerid_storeview2"
                ]
            ]
        ];
    }
}

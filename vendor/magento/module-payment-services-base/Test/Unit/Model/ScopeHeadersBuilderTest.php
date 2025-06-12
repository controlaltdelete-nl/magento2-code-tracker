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

namespace Magento\PaymentServicesBase\Test\Unit\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ScopeHeadersBuilderTest extends TestCase
{

    /**
     * @var MockObject|Config
     */
    private $configMock;

    /**
     * @var MockObject|StoreManagerInterface
     */
    private $storeManagerMock;

    public function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
    }

    /**
     * Creates a dummy test store.
     *
     * @param array $params {websiteId:int, storeId:int, storeViewId:int}
     * @return StoreInterface
     */
    public function fakeStore(array $params): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn($params['websiteId']);
        $store->method('getStoreGroupId')->willReturn($params['storeId']);
        $store->method('getId')->willReturn($params['storeViewId']);
        $store->method('getCode')->willReturn('test-store');
        $store->method('getName')->willReturn('test store');
        return $store;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testWebsiteHeaders(): void
    {
        $this->configMock
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_WEBSITE);

        $this->storeManagerMock->method('getStore')
            ->with('333')
            ->willReturn($this->fakeStore([
                'websiteId' => '111',
                'storeId' => '222',
                'storeViewId' => '333',
            ]));

        $builder = new ScopeHeadersBuilder($this->configMock, $this->storeManagerMock);

        $actualHeaders = $builder->buildScopeHeaders('333');

        $expectedHeaders = [
            'x-scope-type' => 'website',
            'x-scope-id' => '111',
        ];

        $this->assertEquals($expectedHeaders, $actualHeaders);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testStoreviewHeaders(): void
    {
        $this->configMock
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_STORE);

        $this->storeManagerMock->method('getStore')
            ->with('333')
            ->willReturn($this->fakeStore([
                'websiteId' => '111',
                'storeId' => '222',
                'storeViewId' => '333',
            ]));

        $builder = new ScopeHeadersBuilder($this->configMock, $this->storeManagerMock);

        $actualHeaders = $builder->buildScopeHeaders('333');

        $expectedHeaders = [
            'x-scope-type' => 'storeview',
            'x-scope-id' => '333',
        ];

        $this->assertEquals($expectedHeaders, $actualHeaders);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testHeadersForCurrentStore(): void
    {
        $this->configMock
            ->method('getMultiBusinessAccountScopingLevel')
            ->willReturn(ScopeInterface::SCOPE_WEBSITE);

        $this->storeManagerMock->method('getStore')
            ->with(null)
            ->willReturn($this->fakeStore([
                'websiteId' => '111',
                'storeId' => '222',
                'storeViewId' => '333',
            ]));

        $builder = new ScopeHeadersBuilder($this->configMock, $this->storeManagerMock);

        $actualHeaders = $builder->buildScopeHeadersForCurrentStore();

        $expectedHeaders = [
            'x-scope-type' => 'website',
            'x-scope-id' => '111',
        ];

        $this->assertEquals($expectedHeaders, $actualHeaders);
    }
}

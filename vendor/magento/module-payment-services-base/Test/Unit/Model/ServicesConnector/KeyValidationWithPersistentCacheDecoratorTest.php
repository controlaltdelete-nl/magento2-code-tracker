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

namespace Magento\PaymentServicesBase\Test\Unit\Model\ServicesConnector;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\ServicesConnector\Api\KeyValidationInterface;
use PHPUnit\Framework\TestCase;
use Magento\PaymentServicesBase\Model\ServicesConnector\KeyValidationWithPersistentCacheDecorator;
use Magento\PaymentServicesBase\Test\Unit\InMemoryCache;
use Psr\Log\LoggerInterface;

class KeyValidationWithPersistentCacheDecoratorTest extends TestCase
{
    /**
     * @var KeyValidationInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private KeyValidationInterface|\PHPUnit\Framework\MockObject\MockObject $delegate;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var KeyValidationWithPersistentCacheDecorator
     */
    private KeyValidationWithPersistentCacheDecorator $decorator;

    /**
     * Setup the test
     */
    protected function setUp(): void
    {
        $this->delegate = $this->createMock(KeyValidationInterface::class);
        $this->cache = new InMemoryCache();
        $this->decorator = new KeyValidationWithPersistentCacheDecorator(
            $this->delegate,
            $this->cache,
            new Json(),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testDoesNotValidateTheSecondTimeWhenValidatingTheSameExtensionAndEnvironmentTwice(): void
    {
        $extension = 'Magento_PaymentServicesBase';

        $this->delegate->expects($this->once())
            ->method('execute')
            ->with($extension, 'production')
            ->willReturn(true);

        $this->decorator->execute($extension);
        $this->decorator->execute($extension);
    }

    public function testValidatesUsingDelegateAndCachesResultWhenNotPreviouslyCached(): void
    {
        $extension = 'Magento_PaymentServicesBase';
        $cacheKey = 'PAYMENT_SERVICES_SERVICES_CONNECTOR_KEY_VALIDITY'
            . '_Magento_PaymentServicesBase'
            . '_production';
        $this->delegate->expects($this->once())
            ->method('execute')
            ->with($extension, 'production')
            ->willReturn(true);

        $validationResult = $this->decorator->execute($extension);

        $this->assertTrue($validationResult);
        $this->assertEquals('true', $this->cache->load($cacheKey));
    }

    public function testDoesNotValidateAgainAndReturnsCachedResultIfPreviouslyCached(): void
    {
        $extension = 'Magento_PaymentServicesBase';
        $cacheKey = 'PAYMENT_SERVICES_SERVICES_CONNECTOR_KEY_VALIDITY'
            . '_Magento_PaymentServicesBase'
            . '_production';
        $this->cache->save('true', $cacheKey);
        $this->delegate->expects($this->never())->method('execute');

        $validationResult = $this->decorator->execute($extension);

        $this->assertTrue($validationResult);
        $this->assertEquals('true', $this->cache->load($cacheKey));
    }

    public function testValidationResultCachedDifferentlyForDifferentEnvironments(): void
    {
        $extension = 'Magento_PaymentServicesBase';
        $this->delegate->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(true, false);

        $validationResult1 = $this->decorator->execute($extension); // default: 'production'
        $validationResult2 = $this->decorator->execute($extension, 'sandbox');

        $this->assertTrue($validationResult1);
        $this->assertFalse($validationResult2);

        $cacheKey1 = 'PAYMENT_SERVICES_SERVICES_CONNECTOR_KEY_VALIDITY'
            . '_Magento_PaymentServicesBase'
            . '_production';

        $cacheKey2 = 'PAYMENT_SERVICES_SERVICES_CONNECTOR_KEY_VALIDITY'
            . '_Magento_PaymentServicesBase'
            . '_sandbox';

        $this->assertEquals('true', $this->cache->load($cacheKey1));
        $this->assertEquals('false', $this->cache->load($cacheKey2));
    }
}

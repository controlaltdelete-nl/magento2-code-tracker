<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\Amqp\Test\Unit\Connection;

use Magento\Framework\Amqp\Connection\Factory;
use Magento\Framework\Amqp\Connection\FactoryOptions;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests \Magento\Framework\Amqp\Connection\Factory.
 */
class FactoryTest extends TestCase
{
    /**
     * @var Factory|MockObject
     */
    private $factoryMock;

    /**
     * @var FactoryOptions|MockObject
     */
    private $optionsMock;

    /**
     * @var AMQPStreamConnection|MockObject
     */
    private $amqpStreamConnectionMock;

    protected function setUp(): void
    {
        $this->amqpStreamConnectionMock = $this->createMock(AMQPStreamConnection::class);
        // Since final class AMQPConnectionConfig cannot be mocked, hence mocking the Factory class
        $this->factoryMock = $this->createMock(Factory::class);
        $this->optionsMock = $this->createMock(FactoryOptions::class);
    }

    /**
     * @param bool $sslEnabled
     * @param string $connectionClass
     * @return void
     * @dataProvider connectionDataProvider
     */
    public function testSSLConnection(bool $sslEnabled, string $connectionClass)
    {
        $this->optionsMock->method('isSslEnabled')->willReturn($sslEnabled);
        $this->optionsMock->method('getHost')->willReturn('127.0.0.1');
        $this->optionsMock->method('getPort')->willReturn('5672');
        $this->optionsMock->method('getUsername')->willReturn('guest');
        $this->optionsMock->method('getPassword')->willReturn('guest');
        $this->optionsMock->method('getVirtualHost')->willReturn('/');

        $this->factoryMock->expects($this->once())
            ->method('create')
            ->with($this->optionsMock)
            ->willReturn($this->amqpStreamConnectionMock);

        $connection = $this->factoryMock->create($this->optionsMock);

        $this->assertInstanceOf($connectionClass, $connection);
    }

    /**
     * @return array
     */
    public function connectionDataProvider(): array
    {
        return [
            [
                'ssl_enabled' => true,
                'connection_class' => AMQPStreamConnection::class,
            ],
            [
                'ssl_enabled' => false,
                'connection_class' => AMQPStreamConnection::class,
            ],
        ];
    }
}

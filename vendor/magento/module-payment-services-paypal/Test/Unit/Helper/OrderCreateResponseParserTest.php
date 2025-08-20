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

namespace Magento\PaymentServicesPaypal\Test\Unit\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\PaymentServicesPaypal\Helper\OrderCreateResponseParser;
use Magento\Sales\Api\Data\TransactionInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderCreateResponseParserTest extends TestCase
{
    /**
     * @var OrderCreateResponseParser
     */
    private OrderCreateResponseParser $parser;

    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->parser = new OrderCreateResponseParser($logger);
    }

    public function testGetTransactionWithNullTransactions()
    {
        $orderCreateResponse = [
            'id' => 'PAY-123',
            'intent' => TransactionInterface::TYPE_CAPTURE,
            'transactions' => null
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error processing the transaction. Please try again.');

        $this->parser->getTransaction($orderCreateResponse);
    }

    public function testGetTransactionWithNulIntent()
    {
        $orderCreateResponse = [
            'id' => 'PAY-123',
            'transactions' => []
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error processing the transaction. Please try again.');

        $this->parser->getTransaction($orderCreateResponse);
    }

    public function testGetTransactionWithEmptyTransactions()
    {
        $orderCreateResponse = [
            'id' => 'PAY-123',
            'intent' => TransactionInterface::TYPE_CAPTURE,
            'transactions' => []
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error processing the transaction. Please try again.');

        $this->parser->getTransaction($orderCreateResponse);
    }

    public function testGetTransactionWithBothCapturesAndAuthorizationsNull()
    {
        $orderCreateResponse = [
            'id' => 'PAY-123',
            'intent' => TransactionInterface::TYPE_CAPTURE,
            'transactions' => [
                'captures' => null,
                'authorizations' => null
            ]
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error processing the transaction. Please try again.');

        $this->parser->getTransaction($orderCreateResponse);
    }

    public function testGetTransactionWithCapture()
    {
        $capture = ['id' => 'CAP-123', 'amount' => ['value' => '100.00']];
        $orderCreateResponse = [
            'intent' => TransactionInterface::TYPE_CAPTURE,
            'transactions' => [
                'captures' => [$capture]
            ]
        ];

        $result = $this->parser->getTransaction($orderCreateResponse);
        $this->assertEquals($capture, $result);
    }

    public function testGetTransactionWithAuthorization()
    {
        $authorization = ['id' => 'AUTH-123', 'amount' => ['value' => '100.00']];
        $orderCreateResponse = [
            'intent' => MethodInterface::ACTION_AUTHORIZE,
            'transactions' => [
                'authorizations' => [$authorization]
            ]
        ];

        $result = $this->parser->getTransaction($orderCreateResponse);
        $this->assertEquals($authorization, $result);
    }

    public function testIsAuthorization()
    {
        $this->assertTrue($this->parser->isAuthorization(TransactionInterface::TYPE_AUTH));
        $this->assertFalse($this->parser->isAuthorization(TransactionInterface::TYPE_CAPTURE));
        $this->assertFalse($this->parser->isAuthorization('some_other_type'));
    }

    public function testGetTransactionWithMultipleCaptures()
    {
        $firstCapture = ['id' => 'CAP-123', 'amount' => ['value' => '100.00']];
        $secondCapture = ['id' => 'CAP-124', 'amount' => ['value' => '200.00']];
        $orderCreateResponse = [
            'intent' => TransactionInterface::TYPE_CAPTURE,
            'transactions' => [
                'captures' => [$firstCapture, $secondCapture]
            ]
        ];

        $result = $this->parser->getTransaction($orderCreateResponse);
        $this->assertEquals($firstCapture, $result);
    }

    public function testGetTransactionWithMultipleAuthorizations()
    {
        $firstAuth = ['id' => 'AUTH-123', 'amount' => ['value' => '100.00']];
        $secondAuth = ['id' => 'AUTH-124', 'amount' => ['value' => '200.00']];
        $orderCreateResponse = [
            'intent' => MethodInterface::ACTION_AUTHORIZE,
            'transactions' => [
                'authorizations' => [$firstAuth, $secondAuth]
            ]
        ];

        $result = $this->parser->getTransaction($orderCreateResponse);
        $this->assertEquals($firstAuth, $result);
    }
}

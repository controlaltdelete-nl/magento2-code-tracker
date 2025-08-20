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

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\PaymentServicesPaypal\Helper\PaymentSourceResponseProcessor;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PaymentSourceResponseProcessorTest extends TestCase
{
    /**
     * @var PaymentSourceResponseProcessor
     */
    private PaymentSourceResponseProcessor $processor;

    /**
     * @var EncryptorInterface|MockObject
     */
    private EncryptorInterface $encryptor;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var InfoInterface|MockObject
     */
    private InfoInterface $payment;

    protected function setUp(): void
    {
        $this->encryptor = $this->getMockForAbstractClass(EncryptorInterface::class);
        $this->logger = $this->getMockForAbstractClass(LoggerInterface::class);
        $this->payment = $this->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new PaymentSourceResponseProcessor(
            $this->encryptor,
            $this->logger
        );
    }

    public function testParseProcessorResponseInformationWithEmptyResponse()
    {
        $transaction = ['processor_response' => []];

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'No processor response found in payment source response in transaction',
                $transaction
            );

        $this->processor->parseProcessorResponseInformation($transaction, $this->payment);
    }

    public function testParseProcessorResponseInformationWithValidResponse()
    {
        $transaction = [
            'processor_response' => [
                'avs_code' => 'Y',
                'cvv_code' => 'M'
            ]
        ];

        $this->payment->expects($this->once())
            ->method('setCcAvsStatus')
            ->with('Y');

        $this->payment->expects($this->once())
            ->method('setCcCidStatus')
            ->with('M');

        $this->processor->parseProcessorResponseInformation($transaction, $this->payment);
    }

    public function testSaveCardInformationWithNoPaymentSourceDetails()
    {
        $transaction = ['payment_source_details' => null];

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('No payment source details found in payment source response', $transaction);

        $this->processor->saveCardInformation($transaction, $this->payment);
    }

    public function testSaveCardInformationWithNoCardDetails()
    {
        $transaction = [
            'payment_source_details' => [
                'card' => null
            ]
        ];

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('No card details found in payment source response', $transaction);

        $this->processor->saveCardInformation($transaction, $this->payment);
    }

    public function testSaveCardInformationWithNoCardDetailsForApplePay()
    {
        $transaction = [
            'payment_source_details' => [
                'applePay' => null
            ]
        ];

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('No card details found in payment source response', $transaction);

        $this->processor->saveCardInformation($transaction, $this->payment);
    }

    /**
     * @return array
     */
    public function validCardDetailsDataProvider(): array
    {
        $cardDetails = [
            'name' => 'John Doe',
            'bin_details' => ['bin' => '123456'],
            'last_digits' => '7890',
            'brand' => 'VISA',
            'card_expiry_month' => '12',
            'card_expiry_year' => '2025'
        ];

        return [
            'regular_card' => [
                'transaction' => [
                    'payment_source_details' => [
                        'card' => $cardDetails
                    ]
                ]
            ],
            'apple_pay_card' => [
                'transaction' => [
                    'payment_source_details' => [
                        'applePay' => [
                            'card' => $cardDetails
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider validCardDetailsDataProvider
     * @param array $transaction
     */
    public function testSaveCardInformationWithValidCardDetails(array $transaction)
    {
        $cardDetails = $transaction['payment_source_details']['card'] ??
            $transaction['payment_source_details']['applePay']['card'];

        $this->encryptor->expects($this->once())
            ->method('encrypt')
            ->with($cardDetails['bin_details']['bin'])
            ->willReturn('encrypted_bin');

        $this->payment->expects($this->once())
            ->method('setCcOwner')
            ->with($cardDetails['name']);

        $this->payment->expects($this->once())
            ->method('setCcNumberEnc')
            ->with('encrypted_bin');

        $this->payment->expects($this->once())
            ->method('setCcLast4')
            ->with($cardDetails['last_digits']);

        $this->payment->expects($this->once())
            ->method('setCcType')
            ->with($cardDetails['brand']);

        $this->payment->expects($this->once())
            ->method('setCcExpMonth')
            ->with($cardDetails['card_expiry_month']);

        $this->payment->expects($this->once())
            ->method('setCcExpYear')
            ->with($cardDetails['card_expiry_year']);

        $this->payment->expects($this->exactly(5))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use ($cardDetails) {
                static $callCount = 0;
                $expectedCalls = [
                    [OrderPaymentInterface::CC_OWNER, $cardDetails['name']],
                    [OrderPaymentInterface::CC_LAST_4, $cardDetails['last_digits']],
                    [OrderPaymentInterface::CC_TYPE, $cardDetails['brand']],
                    [OrderPaymentInterface::CC_EXP_MONTH, $cardDetails['card_expiry_month']],
                    [OrderPaymentInterface::CC_EXP_YEAR, $cardDetails['card_expiry_year']]
                ];

                $this->assertEquals($expectedCalls[$callCount][0], $key);
                $this->assertEquals($expectedCalls[$callCount][1], $value);
                $callCount++;
            });

        $this->processor->saveCardInformation($transaction, $this->payment);
    }
}

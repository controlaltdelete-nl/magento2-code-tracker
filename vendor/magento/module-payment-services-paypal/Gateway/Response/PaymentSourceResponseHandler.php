<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\PaymentServicesPaypal\Helper\PaymentSourceResponseProcessor;

class PaymentSourceResponseHandler implements HandlerInterface
{
    public const AUTH_TXN = 'authorization';
    public const AUTH_CAPTURE_TXN = 'auth_capture';

    /**
     * @var PaymentSourceResponseProcessor
     */
    private PaymentSourceResponseProcessor $paymentSourceResponseProcessor;

    /**
     * @param PaymentSourceResponseProcessor $paymentSourceResponseProcessor
     */
    public function __construct(PaymentSourceResponseProcessor $paymentSourceResponseProcessor)
    {
        $this->paymentSourceResponseProcessor = $paymentSourceResponseProcessor;
    }

    /**
     * Handles Authorization Responses
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $paymentDO = $handlingSubject['payment'];
        $payment = $paymentDO->getPayment();
        $mpTransaction = $response['mp-transaction'];

        if ($mpTransaction['type'] === self::AUTH_TXN || $mpTransaction['type'] === self::AUTH_CAPTURE_TXN) {

            $this->paymentSourceResponseProcessor->parseProcessorResponseInformation(
                $mpTransaction,
                $payment
            );

            $this->paymentSourceResponseProcessor->saveCardInformation(
                $mpTransaction,
                $payment
            );
        }
    }
}

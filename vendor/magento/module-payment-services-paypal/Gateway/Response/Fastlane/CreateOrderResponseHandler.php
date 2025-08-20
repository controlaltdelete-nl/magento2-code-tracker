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

namespace Magento\PaymentServicesPaypal\Gateway\Response\Fastlane;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\PaymentServicesPaypal\Gateway\Response\TxnIdHandler;
use Magento\PaymentServicesPaypal\Helper\OrderCreateResponseParser;
use Magento\PaymentServicesPaypal\Helper\PaymentSourceResponseProcessor;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class CreateOrderResponseHandler implements HandlerInterface
{
    public const PAYPAL_ORDER_ID = 'paypal_order_id';
    public const PAYMENTS_ORDER_ID = 'payments_order_id';
    public const PAYPAL_ORDER_AMOUNT = 'paypal_order_amount';
    public const PENDING_TXN_ID = 'pending_txn_id';

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * @var OrderCreateResponseParser
     */
    private OrderCreateResponseParser $orderCreateResponseParser;

    /**
     * @var PaymentSourceResponseProcessor
     */
    private PaymentSourceResponseProcessor $paymentSourceResponseProcessor;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderCreateResponseParser $orderCreateResponseParser
     * @param PaymentSourceResponseProcessor $paymentSourceResponseProcessor
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        OrderCreateResponseParser $orderCreateResponseParser,
        PaymentSourceResponseProcessor $paymentSourceResponseProcessor,
        LoggerInterface $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->orderCreateResponseParser = $orderCreateResponseParser;
        $this->paymentSourceResponseProcessor = $paymentSourceResponseProcessor;
        $this->logger = $logger;
    }

    /**
     * Handles Create order Response
     *
     * For Fastlane, we save the Paypal order ID and the Mp order ID in the sales_order_payment object
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $paymentDO = $handlingSubject['payment'];
        $payment = $paymentDO->getPayment();

        if (!empty($response['paypal-order'])) {
            $paypalOrder = $response['paypal-order'];

            // Get the transaction (authorization or capture information) from Paypal response
            $transaction = $this->orderCreateResponseParser->getTransaction($paypalOrder);

            // If transaction is denied, we throw an exception to stop the order creation
            if ($this->orderCreateResponseParser->isDenied($transaction['status'])) {
                $this->logger->error(
                    'Transaction denied for Paypal Order Id and Transaction Id',
                    [$paypalOrder['id'], $transaction['paypal_transaction_id']]
                );

                throw new LocalizedException(__('Transaction denied. Please try again.'));
            }

            // If transaction is pending, save some additional information in the payment
            if ($this->orderCreateResponseParser->isPending($transaction['status'])) {
                $this->handlePendingTransaction($transaction, $payment);
            }

            // Save Paypal order ID and Mp order ID in the payment
            $payment->setAdditionalInformation(self::PAYPAL_ORDER_ID, $paypalOrder['id']);
            $payment->setAdditionalInformation(self::PAYMENTS_ORDER_ID, $paypalOrder['mp_order_id']);

            // Keep transaction open so refund or capture actions can be performed
            $payment->setIsTransactionClosed(false);

            // Save additional transaction information in the payment
            $this->saveTransactionAdditionalInformation($payment, $transaction);

            // Save credit card related information in the payment
            $this->paymentSourceResponseProcessor->parseProcessorResponseInformation($transaction, $payment);
            $this->paymentSourceResponseProcessor->saveCardInformation($transaction, $payment);
        }
    }

    /**
     * Save transaction information: paypalTransaction and amount.
     *
     * If the transaction is an authorization, save the mpTransactionId
     *
     * @param InfoInterface $payment
     * @param array $transaction
     * @return void
     * @throws LocalizedException
     */
    private function saveTransactionAdditionalInformation(InfoInterface $payment, array $transaction): void
    {
        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $payment->setTransactionId($transaction['id']);

        $payment->setAdditionalInformation(
            TxnIdHandler::PAYPAL_TXN_ID_KEY,
            $transaction["paypal_transaction_id"] ?? null
        );

        $payment->setAdditionalInformation(
            self::PAYPAL_ORDER_AMOUNT,
            $transaction['amount']['value'] ?? null
        );

        if ($this->orderCreateResponseParser->isAuthorization($transaction['type'])) {
            $this->saveAuthorizationInformation($payment, $transaction['id']);
        }
    }

    /**
     * Save mpTransactionId in the quote_payment for authorization
     *
     * @param InfoInterface $payment
     * @param string $authorizationId
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function saveAuthorizationInformation(InfoInterface $payment, string $authorizationId): void
    {
        $quoteId = $payment->getOrder()->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);

        $quotePayment = $quote->getPayment();
        $quotePayment->setAdditionalInformation(TxnIdHandler::AUTH_ID_KEY, $authorizationId);
        $this->quoteRepository->save($quote);

        $payment->setAdditionalInformation(TxnIdHandler::AUTH_ID_KEY, $authorizationId);
    }

    /**
     * Handle pending transaction
     *
     * @param array $transaction
     * @param InfoInterface $payment
     * @return void
     */
    private function handlePendingTransaction(array $transaction, InfoInterface $payment): void
    {
        $payment->setIsTransactionPending(true);
        $payment->setAdditionalInformation(self::PENDING_TXN_ID, $transaction['paypal_transaction_id']);
    }
}

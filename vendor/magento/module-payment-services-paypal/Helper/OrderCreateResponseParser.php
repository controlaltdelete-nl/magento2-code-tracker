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

namespace Magento\PaymentServicesPaypal\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Psr\Log\LoggerInterface;

class OrderCreateResponseParser
{
    private const DENIED_STATUS = 'denied';
    private const PENDING_STATUS = 'pending';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return transaction details from the order create response depending on the payment intent
     *
     * As Paypal returns an array of transactions (authorizations or captures), but Magento only supports
     * one transaction, we take the first one from the list.
     *
     * @param array $orderCreateResponse
     * @return array
     * @throws LocalizedException
     */
    public function getTransaction(array $orderCreateResponse): array
    {
        $paymentIntent = $orderCreateResponse['intent'] ?? null;
        $transactions = $orderCreateResponse['transactions'] ?? [];

        if ($paymentIntent ==  null || empty($transactions)) {
            $this->logger->error(
                'No payment intent or transaction found in the Paypal Order response',
                [
                    'paypal_order_id' => $orderCreateResponse['id'],
                    'payment_intent' => $paymentIntent,
                    'transactions' => $transactions,
                ]
            );

            throw new LocalizedException(__('Error processing the transaction. Please try again.'));
        }

        if ($capture = $this->getFirstCaptureFromTransactions($paymentIntent, $transactions)) {
            return $capture;
        }

        if ($authorization = $this->getFirstAuthorizationFromTransactions($paymentIntent, $transactions)) {
            return $authorization;
        }

        $this->logger->error(
            'No valid transactions found in the Paypal Order response',
            [
                'paypal_order_id' => $orderCreateResponse['id'],
                'transactions' => $transactions,
            ]
        );

        throw new LocalizedException(__('Error processing the transaction. Please try again.'));
    }

    /**
     * Is the transaction an authorization
     *
     * @param string $transactionType
     * @return bool
     */
    public function isAuthorization(string $transactionType): bool
    {
        return $transactionType === TransactionInterface::TYPE_AUTH;
    }

    /**
     * Is the transaction denied
     *
     * @param string $status
     * @return bool
     */
    public function isDenied(string $status): bool
    {
        return strcasecmp($status, self::DENIED_STATUS) === 0;
    }

    /**
     * Is the transaction pending
     *
     * @param string $status
     * @return bool
     */
    public function isPending(string $status): bool
    {
        return strcasecmp($status, self::PENDING_STATUS) === 0;
    }

    /**
     * Get the first capture from transactions
     *
     * @param string $paymentIntent
     * @param array $transactions
     * @return array|null
     */
    private function getFirstCaptureFromTransactions(string $paymentIntent, array $transactions): ?array
    {
        if ($paymentIntent == TransactionInterface::TYPE_CAPTURE
            && !empty($transactions['captures']) && is_array($transactions['captures'])) {
            return reset($transactions['captures']);
        }

        return null;
    }

    /**
     * Get the first authorization from transactions
     *
     * @param string $paymentIntent
     * @param array $transactions
     * @return array|null
     */
    private function getFirstAuthorizationFromTransactions(string $paymentIntent, array $transactions): ?array
    {
        if ($paymentIntent == MethodInterface::ACTION_AUTHORIZE
            && !empty($transactions['authorizations']) && is_array($transactions['authorizations'])) {
            return reset($transactions['authorizations']);
        }

        return null;
    }
}

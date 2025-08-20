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

namespace Magento\PaymentServicesPaypalGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\PaymentServicesPaypal\Api\CompleteOrderInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\SalesGraphQl\Model\Formatter\Order as OrderFormatter;

/**
 * Complete Order resolver, used for GraphQL mutation processing.
 */
class CompleteOrderResolver implements ResolverInterface
{
    /**
     * @param CompleteOrderInterface $completeOrder
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderFormatter $orderFormatter
     */
    public function __construct(
        private readonly CompleteOrderInterface $completeOrder,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderFormatter $orderFormatter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $errors = false;
        $order = null;
        $cartId = $this->maskedQuoteIdToQuoteId->execute($args['input']['cartId']);
        $orderId = $args['input']['id'];

        try {
            $orderId = $this->completeOrder->syncAndPlaceOrder($cartId, $orderId);
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException|LocalizedException $exception) {
            $errors = [
                [
                    'message' => $exception->getMessage(),
                    'code' => 'UNDEFINED'
                ]
            ];
        }

        if ($errors) {
            return [
                'errors' => $errors
            ];
        }

        return [
            'order' => [
                'order_number' => $order->getIncrementId(),
                // @deprecated The order_id field is deprecated, use order_number instead
                'order_id' => $order->getIncrementId(),
            ],
            'orderV2' => $this->orderFormatter->format($order),
            'errors' => []
        ];
    }
}

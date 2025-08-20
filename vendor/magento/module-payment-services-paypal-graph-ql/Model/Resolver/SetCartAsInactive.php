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

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Psr\Log\LoggerInterface;

class SetCartAsInactive implements ResolverInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->logger = $logger;
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
        if (empty($args['cartId']) || !is_string($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }

        try {
            $this->setCartAsInactive($args['cartId']);
            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'success' => false,
                'error' => "Error when setting the cart " . $args['cartId'] . " as inactive - " . $e->getMessage()
            ];
        }
    }

    /**
     * @param string $maskedCartId
     * @return void
     * @throws NoSuchEntityException
     */
    private function setCartAsInactive(string $maskedCartId): void
    {
        $cartId = $this->maskedQuoteIdToQuoteId->execute($maskedCartId);
        $cart = $this->cartRepository->get($cartId);
        if ($cart->getIsActive()) {
            $cart->setIsActive(false);
            $this->cartRepository->save($cart);
            $this->logger->info("Cart with ID " . $maskedCartId . " has been set as inactive.");
        } else {
            $this->logger->info("Cart with ID " . $maskedCartId . " was already set as inactive.");
        }
    }

}

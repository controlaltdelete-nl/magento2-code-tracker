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

namespace Magento\PaymentServicesPaypal\Model\ShippingCallback;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

/**
 * Retrieve quote from masked ID
 */
class QuoteRetriever
{
    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly CartRepositoryInterface $cartRepository,
    ) {
    }

    /**
     * Get quote by masked ID
     *
     * @param string $cartId
     * @return CartInterface
     * @throws LocalizedException
     */
    public function getQuoteByMaskedId(string $cartId): CartInterface
    {
        $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        $quote = $this->cartRepository->getActive($quoteId);

        if (!$quote->getId() || count($quote->getAllItems()) === 0) {
            throw new LocalizedException(
                __('Could not find cart with ID "%1". Please try again', $quote->getId())
            );
        }

        return $quote;
    }
}

<?php

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use \Magento\Store\Model\Information as Config;
use Psr\Log\LoggerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;

class L3DataProvider
{
    private const DEFAULT_UNIT_OF_MEASURE = 'ITM';
    private const DEFAULT_UPC_TYPE = 'UPC-A';
    private const MAX_COMMODITY_CODE_LENGTH = 12;
    private const MIN_UPC_CODE_LENGTH = 6;
    private const MAX_UPC_CODE_LENGTH = 17;
    private const MAX_DESCRIPTION_LENGTH = 127;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var LoggerInterface $logger
     */
    private LoggerInterface $logger;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * Get L3 data for the given cart
     *
     * @param Quote $quote
     * @return array
     */
    public function getL3Data(Quote $quote): array
    {
        try {
            $totals = $this->getQuoteTotals($quote);

            return [
                'ships_from_postal_code' => $this->extractShipsFromPostalCode($quote),
                'shipping_amount' => $this->extractShippingAmount($totals, $quote),
                'discount_amount' => $this->extractDiscount($totals, $quote),
                'line_items' => $this->extractItems($quote),
            ];
        } catch (\Exception $e) {
            $this->logger->error(
                'Error extracting L3 data',
                ['exception' => $e->getMessage()]
            );

            return [];
        }
    }

    /**
     * Extract the totals from the quote
     *
     * @param Quote $quote
     * @return array
     */
    private function getQuoteTotals(Quote $quote): array
    {
        $quote->collectTotals();

        if ($quote->isVirtual()) {
            $totals = $quote->getBillingAddress()->getTotals();
        } else {
            $totals = $quote->getShippingAddress()->getTotals();
        }

        return $totals;
    }

    /**
     * Extract the ships from postal code from the store configuration
     *
     * @param Quote $quote
     * @return string
     */
    private function extractShipsFromPostalCode(Quote $quote): string
    {
        return $quote->getStore()->getConfig(Config::XML_PATH_STORE_INFO_POSTCODE) ?? '';
    }

    /**
     * Extract the shipping amount from the quote
     *
     * @param array $totals
     * @param Quote $quote
     * @return array
     */
    private function extractShippingAmount(array $totals, Quote $quote) : array
    {
        $amount = (isset($totals['shipping']) && !empty($totals['shipping']->getValue()))
            ? (float) $totals['shipping']->getValue()
            : 0.00;

        return [
            'value' => $this->formatAmount($amount),
            'currency_code' => $quote->getCurrency()->getBaseCurrencyCode()
        ];
    }

    /**
     * Extract the discount amount from the quote
     *
     * @param array $totals
     * @param Quote $quote
     * @return array
     */
    private function extractDiscount(array $totals, Quote $quote) : array
    {
        $amount = (isset($totals['discount']) && !empty($totals['discount']->getValue()))
            ? abs((float) $totals['discount']->getValue())
            : 0.00;

        return [
            'value' => $this->formatAmount($amount),
            'currency_code' => $quote->getCurrency()->getBaseCurrencyCode()
        ];
    }

    /**
     * Extract the items from the quote
     *
     * @param Quote $quote
     * @return array
     *
     * @throws NoSuchEntityException
     */
    private function extractItems(Quote $quote) : array
    {
        return array_map(
            fn(QuoteItem $item) : array => [
                    'name' => $item->getName(),
                    'quantity' => (string) $item->getQty(),
                    'unit_amount' => [
                        'value' => $this->formatAmount((float)$item->getBasePrice()),
                        'currency_code' => $quote->getCurrency()->getBaseCurrencyCode()
                    ],
                    'tax' => [
                        'value' => $this->formatAmount((float)$item->getTaxAmount() ?? 0.00),
                        'currency_code' => $quote->getCurrency()->getBaseCurrencyCode()
                    ],
                    'discount_amount' => [
                        'value' => $this->formatAmount((float)$item->getDiscountAmount() ?? 0.00),
                        'currency_code' => $quote->getCurrency()->getBaseCurrencyCode()
                    ],
                    'unit_of_measure' => self::DEFAULT_UNIT_OF_MEASURE,
                    'commodity_code' => $this->formatCommodityCode($item->getSku()),
                    'upc' => [
                        'type' => self::DEFAULT_UPC_TYPE,
                        'code' => $this->formatUPCCode((string)$item->getProduct()->getId()),
                    ],
                    'description' => $this->formatDescription((int)$item->getProduct()->getId()),
                ],
            $quote->getAllVisibleItems()
        );
    }

    /**
     * Format the amount with two decimal places
     *
     * @param float $amount
     * @return string
     */
    private function formatAmount(float $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    /**
     * Truncate the commodity code
     *
     * @param string $sku
     * @return string
     */
    private function formatCommodityCode(string $sku): string
    {
        return substr($sku, 0, self::MAX_COMMODITY_CODE_LENGTH);
    }

    /**
     * Format the UPC code with type and value
     *
     * @param string $productId
     * @return string
     */
    private function formatUPCCode(string $productId): string
    {
        $trimmedCode = substr($productId, 0, self::MAX_UPC_CODE_LENGTH);
        return str_pad($trimmedCode, self::MIN_UPC_CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Format the description for the given product
     *
     * @param int $productId
     * @return string
     *
     * @throws NoSuchEntityException
     */
    private function formatDescription(int $productId): string
    {
        $product = $this->productRepository->getById($productId);

        $description = $product->getShortDescription() ?? $product->getDescription() ?? $product->getName();

        return substr(strip_tags($description), 0, self::MAX_DESCRIPTION_LENGTH);
    }
}

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

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

class ResponseBuilder
{
    /**
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param OrderHelper $orderHelper
     * @param ShippingProcessor $shippingProcessor
     * @param MerchantService $merchantService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ScopeHeadersBuilder $scopeHeaderBuilder,
        private readonly OrderHelper $orderHelper,
        private readonly ShippingProcessor $shippingProcessor,
        private readonly MerchantService $merchantService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Build merchant response
     *
     * @param CartInterface|Quote $quote
     * @param array $requestData
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function buildResponse(CartInterface|Quote $quote, array $requestData): array
    {
        try {
            $shippingMethods = $this->shippingProcessor->getShippingMethods($quote);
            $shippingMethods = $this->markSelectedMethod($quote, $shippingMethods);
            $shippingMethods = $this->shippingProcessor->setDefaultShippingMethod($quote, $shippingMethods);

            $this->collectTotals($quote);
            $payPalMerchantId = $this->getPayPalMerchantId($quote);
        } catch (Exception $e) {
            $this->logger->error($e);
            throw new LocalizedException(__($e->getMessage()));
        }

        return [
            'id' => $payPalMerchantId,
            'purchase_units' => [
                [
                    'reference_id' => $requestData['purchase_units'][0]['reference_id'],
                    'amount' => $this->getAmountData($quote),
                    'items' => $this->orderHelper->getLineItems($quote, $quote->getReservedOrderId()),
                    'shipping_options' => $shippingMethods
                ]
            ]
        ];
    }

    /**
     * Mark selected shipping method
     *
     * @param CartInterface|Quote $quote
     * @param array $shippingMethods
     * @return array
     */
    private function markSelectedMethod(CartInterface|Quote $quote, array $shippingMethods): array
    {
        $selectedMethod = $quote->getShippingAddress()->getShippingMethod();

        if ($selectedMethod) {
            foreach ($shippingMethods as $key => $method) {
                if ($selectedMethod === $method['id']) {
                    $shippingMethods[$key]['selected'] = true;
                }
            }
        }

        return $shippingMethods;
    }

    /**
     * Collect quote totals
     *
     * @param CartInterface|Quote $quote
     * @return void
     */
    private function collectTotals(CartInterface|Quote $quote): void
    {
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
    }

    /**
     * Get PayPal Merchant ID
     *
     * @param Quote $quote
     * @return string
     * @throws NoSuchEntityException
     */
    private function getPayPalMerchantId(Quote $quote): string
    {
        $scopeData = $this->scopeHeaderBuilder->buildScopeHeaders($quote->getStore());

        $response = $this->merchantService->getMerchantAndPartnerInformation(
            $scopeData['x-scope-type'],
            (int) $scopeData['x-scope-id']
        );

        return $response['merchantIdentifier'];
    }

    /**
     * Get amount data
     *
     * @param CartInterface|Quote $quote
     * @return array
     */
    private function getAmountData(CartInterface|Quote $quote): array
    {
        $amountData = [
            'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
            'value' => $this->orderHelper->formatAmount((float) $quote->getBaseGrandTotal())
        ];

        $breakDown = $this->orderHelper->getAmountBreakdown($quote, $quote->getReservedOrderId());
        if (!empty($breakDown)) {
            $amountData['breakdown'] = $breakDown;
        }

        return $amountData;
    }
}

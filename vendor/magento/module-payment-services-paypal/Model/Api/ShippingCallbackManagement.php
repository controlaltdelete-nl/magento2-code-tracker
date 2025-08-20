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

namespace Magento\PaymentServicesPaypal\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PaymentServicesPaypal\Api\ShippingCallbackManagementInterface;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\AddressValidator;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\ShippingProcessor;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\QuoteRetriever;
use Magento\PaymentServicesPaypal\Model\ShippingCallback\ResponseBuilder;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class ShippingCallbackManagement implements ShippingCallbackManagementInterface
{
    /**
     * @param Json $json
     * @param QuoteRetriever $quoteRetriever
     * @param AddressValidator $addressValidator
     * @param ShippingProcessor $shippingProcessor
     * @param ResponseBuilder $responseBuilder
     */
    public function __construct(
        private readonly Json $json,
        private readonly QuoteRetriever $quoteRetriever,
        private readonly AddressValidator $addressValidator,
        private readonly ShippingProcessor $shippingProcessor,
        private readonly ResponseBuilder $responseBuilder
    ) {
    }

    /**
     * Handle PayPal shipping callback
     *
     * @param string $cartId
     * @param string $sessionId
     * @param string $requestBody
     * @return array
     * @throws LocalizedException
     */
    public function execute(
        string $cartId,
        string $sessionId,
        string $requestBody
    ): array {
        $requestData = $this->json->unserialize($requestBody);

        // Validate basic request structure
        if (!isset($requestData['id'])) {
            throw new LocalizedException(__('Missing PayPal order ID'));
        }

        $quote = $this->quoteRetriever->getQuoteByMaskedId($cartId);
        if ($sessionId !== $quote->getPayment()->getAdditionalInformation('session_id')) {
            throw new LocalizedException(__('Unable to process shipping callback'));
        }

        return $this->processShippingCallback($quote, $requestData);
    }

    /**
     * Process shipping callback
     *
     * @param CartInterface|Quote $quote
     * @param array $requestData
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function processShippingCallback(
        CartInterface|Quote $quote,
        array $requestData
    ): array {
        $shippingAddress = $requestData['shipping_address'] ?? null;
        $shippingOption = $requestData['shipping_option'] ?? null;

        if (!$shippingAddress && !$shippingOption) {
            return [];
        }

        if ($shippingAddress) {
            $this->addressValidator->validateAndSetAddress($quote, $shippingAddress);
        }

        if ($shippingOption) {
            $this->shippingProcessor->processShippingOptions($quote, $shippingOption);
        }

        return $this->responseBuilder->buildResponse($quote, $requestData);
    }
}

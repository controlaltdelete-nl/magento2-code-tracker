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

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Helper\TextSanitiser;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Builds the body for PayPal order action request.
 */
class PaypalOrderRequestBuilder
{
    private const PAYPAL_ORDER = 'paypal-order';
    private const PAYPAL_ORDER_UPDATE = 'paypal-order-update';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * @var TextSanitiser
     */
    private TextSanitiser $textSanitiser;

    /**
     * @param Config $config
     * @param CartRepositoryInterface $quoteRepository
     * @param TextSanitiser $textSanitiser
     */
    public function __construct(
        Config $config,
        CartRepositoryInterface $quoteRepository,
        TextSanitiser $textSanitiser
    ) {
        $this->config = $config;
        $this->quoteRepository = $quoteRepository;
        $this->textSanitiser = $textSanitiser;
    }

    /**
     * Build the body for PayPal order create request
     *
     * @param array $data
     * @param StoreInterface $store
     * @return array
     * @throws NoSuchEntityException
     */
    public function buildCreateRequestBody(array $data, StoreInterface $store): array
    {
        $body = [
            self::PAYPAL_ORDER => [
                'amount' => [
                    'currency_code' => $data['currency_code'],
                    'value' => $data['amount'] ?? 0.00
                ],
                'is_digital' => !!$data['is_digital'] ?? false,
                'website_id' => $store->getWebsiteId(),
                'store_id' => $store->getStoreGroupId(),
                'storeview_id' => $store->getId(),
                'payment_source' => $this->resolvePaymentSource($data),
                'vault' => $data['vault'] ?? false,
                'three_ds_mode' => $this->resolveThreeDSMode($data),
            ]
        ];

        $body = $this->addAddresses($body, $data);
        $body = $this->addPayer($body, $data);
        $body = $this->addIntent($body, $data);
        $body = $this->addOrderIncrementId($body, $data);
        $body = $this->addSoftDescriptor($body, $data);
        $body = $this->applyL2Data($body, $data);
        $body = $this->applyL3Data($body, $data);
        $body = $this->applyLineItems($body, $data);
        $body = $this->applyAmountBreakdown($body, $data, self::PAYPAL_ORDER);
        $body = $this->applyCheckoutLocation($body, $data);
        $body = $this->applyPaymentSourceDetails($body, $data);
        $body = $this->applyUserAction($body, $data);
        $body = $this->applyShippingPreference($body, $data);
        $body = $this->applyOrderUpdateCallbackConfig($body, $data);

        return $body;
    }

    /**
     * Build the body for PayPal order update request
     *
     * @param array $data
     * @return array
     */
    public function buildUpdateRequestBody(array $data): array
    {
        $body = [
            self::PAYPAL_ORDER_UPDATE => [
                'reference_id' => 'default',
                'amount' => [
                    'operation' => 'REPLACE',
                    'value' => [
                        'currency_code' => $data['currency_code'],
                        'value' => $data['amount']
                    ]
                ]
            ]
        ];

        $body = $this->applyLineItemsOperation($body, $data);
        $body = $this->applyAmountBreakdown($body, $data, self::PAYPAL_ORDER_UPDATE);

        return $body;
    }

    /**
     * Add shipping and billing addresses
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function addAddresses(array $order, array $data): array
    {
        $order[self::PAYPAL_ORDER]['shipping-address'] = $data['shipping_address'] ?? null;
        $order[self::PAYPAL_ORDER]['billing-address'] = $data['billing_address'] ?? null;
        return $order;
    }

    /**
     * Add payer information
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function addPayer(array $order, array $data): array
    {
        $order[self::PAYPAL_ORDER]['payer'] = $data['payer'] ?? null;
        return $order;
    }

    /**
     * Add payment intent if quote_id exists
     *
     * @param array $order
     * @param array $data
     * @return array
     * @throws NoSuchEntityException
     */
    private function addIntent(array $order, array $data): array
    {
        if ($data['quote_id'] !== null) {
            $order[self::PAYPAL_ORDER]['intent'] = $this->getPaymentIntent($data['quote_id']);
        }
        return $order;
    }

    /**
     * Add order increment ID if present
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function addOrderIncrementId(array $order, array $data): array
    {
        if (!empty($data['order_increment_id'])) {
            $order[self::PAYPAL_ORDER]['order_increment_id'] = $data['order_increment_id'];
        }
        return $order;
    }

    /**
     * Add soft descriptor if configured
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function addSoftDescriptor(array $order, array $data): array
    {
        $softDescriptor = $this->config->getSoftDescriptor($data['storeview_code'] ?? null);
        if ($softDescriptor) {
            $order[self::PAYPAL_ORDER]['soft_descriptor'] = $softDescriptor;
        }
        return $order;
    }

    /**
     * Get the payment intent (authorize/capture) of the quote
     *
     * @param string $quoteId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getPaymentIntent(string $quoteId): string
    {
        $quote = $this->quoteRepository->get($quoteId);
        $paymentMethod = $quote->getPayment()->getMethod();
        $storeId = $quote->getStoreId();
        if ($paymentMethod === HostedFieldsConfigProvider::CC_VAULT_CODE) {
            return $this->config->getPaymentIntent(HostedFieldsConfigProvider::CODE, $storeId);
        }
        return $this->config->getPaymentIntent($paymentMethod, $storeId);
    }

    /**
     * Apply L2 data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyL2Data(array $order, array $data) : array
    {
        if (empty($data['l2_data'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['l2_data'] = $data['l2_data'];
        return $order;
    }

    /**
     * Apply L3 data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyL3Data(array $order, array $data) : array
    {
        if (empty($data['l3_data'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['l3_data'] = $data['l3_data'];
        return $order;
    }

    /**
     * Apply Line items data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyLineItems(array $order, array $data) : array
    {
        if (empty($data['line_items'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['line_items'] = $data['line_items'];
        return $order;
    }

    /**
     * Apply checkout location data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyCheckoutLocation(array $order, array $data): array
    {
        if (empty($data['location']) || !in_array($data['location'], Config::CHECKOUT_LOCATIONS)) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['location'] = $data['location'];
        return $order;
    }

    /**
     * Get and sanitise the payment source
     *
     * @param array $data
     * @return string
     */
    private function resolvePaymentSource(array $data): string
    {
        return empty($data['payment_source']) ? '' : $this->textSanitiser->textOnly($data['payment_source']);
    }

    /**
     * Get and sanitise the 3DS mode
     *
     * @param array $data
     * @return string|null
     */
    private function resolveThreeDSMode(array $data): ?string
    {
        return empty($data['three_ds_mode']) ? null : $this->textSanitiser->textOnly($data['three_ds_mode']);
    }

    /**
     * Apply Amount Breakdown data to the order
     *
     * @param array $order
     * @param array $data
     * @param string $key
     * @return array
     */
    private function applyAmountBreakdown(array $order, array $data, string $key) : array
    {
        if (empty($data['amount_breakdown'])) {
            return $order;
        }

        $order[$key]['amount_breakdown'] = $data['amount_breakdown'];
        return $order;
    }

    /**
     * Apply Line items operation data to the order
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyLineItemsOperation(array $order, array $data) : array
    {
        if (empty($data['line_items'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER_UPDATE]['line_items'] = [
            'operation' => 'ADD',
            'value' => $data['line_items']
        ];

        return $order;
    }

    /**
     * Apply Payment Source Details
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyPaymentSourceDetails(array $order, array $data) : array
    {
        if (empty($data['payment_source_details'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['payment_source_details'] = $data['payment_source_details'];

        return $order;
    }

    /**
     * Apply 'user_action'
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyUserAction(array $order, array $data): array
    {
        if (empty($data['user_action'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['user_action'] = $data['user_action'];

        return $order;
    }

    /**
     * Apply 'shipping_preference'
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyShippingPreference(array $order, array $data): array
    {
        if (empty($data['shipping_preference'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['shipping_preference'] = $data['shipping_preference'];

        return $order;
    }

    /**
     * Apply 'order_update_callback_config'
     *
     * @param array $order
     * @param array $data
     * @return array
     */
    private function applyOrderUpdateCallbackConfig(array $order, array $data): array
    {
        if (empty($data['order_update_callback_config'])) {
            return $order;
        }

        $order[self::PAYPAL_ORDER]['order_update_callback_config'] = $data['order_update_callback_config'];

        return $order;
    }
}

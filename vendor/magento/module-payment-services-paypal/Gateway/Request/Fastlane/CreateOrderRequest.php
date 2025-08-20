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

namespace Magento\PaymentServicesPaypal\Gateway\Request\Fastlane;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\PaymentServicesPaypal\Model\CustomerHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesPaypal\Model\PaypalOrderRequestBuilder;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreateOrderRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ScopeHeadersBuilder
     */
    private ScopeHeadersBuilder $scopeHeaderBuilder;

    /**
     * @var CustomerHeadersBuilder
     */
    private CustomerHeadersBuilder $customerHeaderBuilder;

    /**
     * @var PaypalOrderRequestBuilder
     */
    private PaypalOrderRequestBuilder $paypalOrderRequestBuilder;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * @var OrderHelper
     */
    private OrderHelper $orderHelper;

    /**
     * @var OrderService
     */
    private OrderService $orderService;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param Config $config
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param CustomerHeadersBuilder $customerHeaderBuilder
     * @param PaypalOrderRequestBuilder $paypalOrderRequestBuilder
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderHelper $orderHelper
     * @param OrderService $orderService
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Config $config,
        ScopeHeadersBuilder $scopeHeaderBuilder,
        CustomerHeadersBuilder $customerHeaderBuilder,
        PaypalOrderRequestBuilder $paypalOrderRequestBuilder,
        CartRepositoryInterface $quoteRepository,
        OrderHelper $orderHelper,
        OrderService $orderService,
        EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->scopeHeaderBuilder = $scopeHeaderBuilder;
        $this->customerHeaderBuilder = $customerHeaderBuilder;
        $this->paypalOrderRequestBuilder = $paypalOrderRequestBuilder;
        $this->quoteRepository = $quoteRepository;
        $this->orderHelper = $orderHelper;
        $this->orderService = $orderService;
        $this->encryptor = $encryptor;
    }

    /**
     * Build create order request
     *
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $paymentDO->getPayment();

        $quoteId = $payment->getOrder()->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);

        if (!$quote) {
            throw new NoSuchEntityException(__('Quote not found'));
        }

        $path = '/' . $this->config->getMerchantId() . '/payment/paypal/order';

        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($payment->getOrder()->getStoreId()),
            $this->customerHeaderBuilder->buildCustomerHeaders($paymentDO),
        );

        $body = $this->paypalOrderRequestBuilder->buildCreateRequestBody(
            $this->getOrderData($quote),
            $quote->getStore()
        );

        return [
            'uri' => $path,
            'method' => Http::METHOD_POST,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    /**
     * Get order data for request
     *
     * @param CartInterface $quote
     * @return array
     */
    private function getOrderData(CartInterface $quote): array
    {
        $paymentSource = $quote->getPayment()->getAdditionalInformation('payment_source');
        $orderIncrementId = $this->orderHelper->reserveAndGetOrderIncrementId($quote);
        $paypalFastlaneToken = $this->encryptor->decrypt(
            $quote->getPayment()->getAdditionalInformation('paypal_fastlane_token')
        );

        return [
            'amount' => $this->orderHelper->formatAmount((float)$quote->getBaseGrandTotal()),
            'l2_data' => $this->orderHelper->getL2Data($quote, $paymentSource ?? ''),
            'l3_data' => $this->orderHelper->getL3Data($quote, $paymentSource ?? ''),
            'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
            'is_digital' => $quote->isVirtual(),
            'storeview_code' => $quote->getStoreId(),
            'payment_source' => $paymentSource,
            'payment_source_details' => [
                'card' => [
                    'single_use_token' => $paypalFastlaneToken,
                ]
            ],
            'quote_id' => $quote->getId(),
            'payer' => $this->orderService->buildGuestPayer($quote),
            'shipping_address' => $this->orderService->mapAddress($quote->getShippingAddress()),
            'billing_address' => $this->orderService->mapAddress($quote->getBillingAddress()),
            'order_increment_id' => $orderIncrementId,
            'line_items' => $this->orderHelper->getLineItems($quote, $orderIncrementId),
            'amount_breakdown' => $this->orderHelper->getAmountBreakdown($quote, $orderIncrementId),
            'location' => Config::CHECKOUT_CHECKOUT_LOCATION
        ];
    }
}

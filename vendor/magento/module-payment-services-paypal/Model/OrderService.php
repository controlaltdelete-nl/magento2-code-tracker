<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Magento\PaymentServicesPaypal\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\Framework\App\Request\Http;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as Address;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Psr\Log\LoggerInterface;

class OrderService
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ServiceClientInterface
     */
    private $httpClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BaseConfig
     */
    private $baseConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param ServiceClientInterface $httpClient
     * @param Config $config
     * @param BaseConfig $baseConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        ServiceClientInterface $httpClient,
        Config $config,
        BaseConfig $baseConfig,
        LoggerInterface $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->baseConfig = $baseConfig;
        $this->logger = $logger;
    }

    /**
     * Map DTO fields and send the order creation request to the backend service
     *
     * @param array $data
     * @return array
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function create(array $data) : array
    {
        $order = [
            'paypal-order' => [
                'amount' => [
                    'currency_code' => $data['currency_code'],
                    'value' => $data['amount'] ?? 0.00
                ],
                'is_digital' => !!$data['is_digital'] ?? false,
                'website_id' => $data['website_id'],
                'payment_source' => $data['payment_source'] ?? '',
                'vault' => $data['vault'] ?? false,
            ]
        ];
        $order['paypal-order']['shipping-address'] = $data['shipping_address'] ?? null;
        $order['paypal-order']['billing-address'] = $data['billing_address'] ?? null;
        $order['paypal-order']['payer'] = $data['payer'] ?? null;
        if ($data['quote_id'] !== null) {
            $order['paypal-order']['intent'] = $this->getPaymentIntent($data['quote_id']);
        }
        if (!empty($data['order_increment_id'])) {
            $order['paypal-order']['order_increment_id'] = $data['order_increment_id'];
        }
        $softDescriptor = $this->config->getSoftDescriptor($data['store_code'] ?? null);
        if ($softDescriptor) {
            $order['paypal-order']['soft_descriptor'] = $softDescriptor;
        }
        $headers = [
            'Content-Type' => 'application/json',
            'x-scope-id' => $data['website_id']
        ];
        if (isset($data['vault']) && $data['vault']) {
            $headers['x-commerce-customer-id'] = $data['payer']['customer_id'];
        }
        if (isset($data['quote_id']) && $data['quote_id']) {
            $headers['x-commerce-quote-id'] = $data['quote_id'];
        }

        $order = $this->applyL2Data($order, $data);
        $order = $this->applyL3Data($order, $data);
        $path = '/payments/' . $this->config->getMerchantId() . '/payment/paypal/order';
        $body = json_encode($order);

        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_POST,
            $body,
            'json',
            $this->baseConfig->getEnvironmentType($data['store_code'] ?? null)
        );

        $this->logger->debug(
            var_export(
                [
                    'request' => [
                        $path,
                        $headers,
                        Http::METHOD_POST,
                        $body
                    ],
                    'response' => $response
                ],
                true
            )
        );

        return $response;
    }

    /**
     * Update the PayPal order with selective params
     *
     * @param string $id
     * @param array $data
     * @throws HttpException
     */
    public function update(string $id, array $data) : void
    {
        $order = [
            'paypal-order-update' => [
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

        $path = '/payments/' . $this->config->getMerchantId() . '/payment/paypal/order/' . $id;
        $headers = ['Content-Type' => 'application/json'];
        $body = json_encode($order);
        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_PATCH,
            $body
        );

        $this->logger->debug(
            var_export(
                [
                    'request' => [
                        $path,
                        $headers,
                        Http::METHOD_PATCH,
                        $body
                    ],
                    'response' => $response
                ],
                true
            )
        );

        if (!isset($response['is_successful']) || !$response['is_successful']) {
            throw new HttpException('Failed to update an order.');
        }
    }

    /**
     * Get the Order object from PayPal
     *
     * @param string $id
     * @return array
     * @throws HttpException
     */
    public function get(string $id) : array
    {
        $response = $this->httpClient->request(
            ['Content-Type' => 'application/json'],
            '/payments/' . $this->config->getMerchantId() . '/payment/paypal/order/' . $id,
            Http::METHOD_GET,
        );
        if (!$response['is_successful']) {
            throw new HttpException('Failed to retrieve an order.');
        }
        return $response;
    }

    /**
     * Map Commerce address fields to DTO
     *
     * @param Address $address
     * @return array|null
     */
    public function mapAddress(Address $address) :? array
    {
        if ($address->getCountry() === null) {
            return null;
        }
        return [
            'full_name' => $address->getFirstname() . ' ' . $address->getLastname(),
            'address_line_1' => $address->getStreet()[0],
            'address_line_2' => $address->getStreet()[1] ?? null,
            'admin_area_1' => $address->getRegionCode(),
            'admin_area_2' => $address->getCity(),
            'postal_code' => $address->getPostcode(),
            'country_code' => $address->getCountry()
        ];
    }

    /**
     * Build the Payer object for PayPal order creation
     *
     * @param Quote $quote
     * @param String $customerId
     * @return array
     */
    public function buildPayer(Quote $quote, String $customerId) : array
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'name' => [
                'given_name' => $quote->getCustomerFirstname(),
                'surname' => $quote->getCustomerLastname()
            ],
            'email' => $quote->getCustomerEmail(),
            'phone_number' => $billingAddress->getTelephone() ?? null,
            'customer_id' => $customerId
        ];
    }

    /**
     * Build Guest Payer object for PayPal order creation
     *
     * @param Quote $quote
     * @return array
     */
    public function buildGuestPayer(Quote $quote) : array
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'name' => [
                'given_name' => $billingAddress->getFirstname(),
                'surname' => $billingAddress->getLastname()
            ],
            'email' => $billingAddress->getEmail(),
            'phone_number' => $billingAddress->getTelephone() ?? null
        ];
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

        $order['paypal-order']['l2_data'] = $data['l2_data'];
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

        $order['paypal-order']['l3_data'] = $data['l3_data'];
        return $order;
    }
}

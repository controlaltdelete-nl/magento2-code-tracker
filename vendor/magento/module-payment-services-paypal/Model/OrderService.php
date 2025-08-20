<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Magento\PaymentServicesPaypal\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\Framework\App\Request\Http;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as Address;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderService
{
    /**
     * @var ServiceClientInterface
     */
    private ServiceClientInterface $httpClient;

    /**
     * @var ScopeHeadersBuilder
     */
    private ScopeHeadersBuilder $scopeHeaderBuilder;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var BaseConfig
     */
    private BaseConfig $baseConfig;

    /**
     * @var PaypalOrderRequestBuilder
     */
    private PaypalOrderRequestBuilder $paypalOrderRequestBuilder;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ServiceClientInterface $httpClient
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param Config $config
     * @param BaseConfig $baseConfig
     * @param PaypalOrderRequestBuilder $paypalOrderRequestBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        ServiceClientInterface $httpClient,
        ScopeHeadersBuilder $scopeHeaderBuilder,
        Config $config,
        BaseConfig $baseConfig,
        PaypalOrderRequestBuilder $paypalOrderRequestBuilder,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->scopeHeaderBuilder = $scopeHeaderBuilder;
        $this->config = $config;
        $this->baseConfig = $baseConfig;
        $this->paypalOrderRequestBuilder = $paypalOrderRequestBuilder;
        $this->logger = $logger;
    }

    /**
     * Map DTO fields and send the order creation request to the backend service
     *
     * @param StoreInterface $store
     * @param array $data
     * @return array
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function create(StoreInterface $store, array $data) : array
    {
        $order = $this->paypalOrderRequestBuilder->buildCreateRequestBody($data, $store);

        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($store)
        );
        if (isset($data['vault']) && $data['vault']) {
            $headers['x-commerce-customer-id'] = $data['payer']['customer_id'];
        }
        if (isset($data['quote_id']) && $data['quote_id']) {
            $headers['x-commerce-quote-id'] = $data['quote_id'];
        }

        $path = '/' . $this->config->getMerchantId() . '/payment/paypal/order';
        $body = json_encode($order);

        if (!$body) {
            $this->logger->error('Error encoding body for order creation request', $order);
            throw new HttpException('Error encoding body for order creation request');
        }

        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_POST,
            $body,
            'json',
            $this->baseConfig->getEnvironmentType($data['storeview_code'] ?? null)
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
     * @param string $storeId
     * @param string $orderId
     * @param array $data
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function update(string $storeId, string $orderId, array $data) : void
    {
        $order = $this->paypalOrderRequestBuilder->buildUpdateRequestBody($data);

        $path = '/' . $this->config->getMerchantId() . '/payment/paypal/order/' . $orderId;
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($storeId)
        );
        $body = json_encode($order);

        if (!$body) {
            $this->logger->error(
                sprintf('Error encoding body for order update request for order id %s', $orderId),
                $order
            );
            throw new HttpException('Error encoding body for order update request');
        }

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
     * Add tracking information for a Paypal Order
     *
     * @param StoreInterface $store
     * @param string $orderId
     * @param array $data
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function track(StoreInterface $store, string $orderId, array $data) : void
    {
        $path = sprintf('/%s/payment/paypal/order/%s/tracking-info', $this->config->getMerchantId(), $orderId);
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($store)
        );
        $body = json_encode($data);

        if (!$body) {
            $this->logger->error(
                sprintf('Error encoding body for tracking info request for order id %s', $orderId),
                $data
            );
            throw new HttpException('Error encoding body for tracking info request');
        }

        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_POST,
            $body
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

        if (!isset($response['is_successful']) || !$response['is_successful']) {
            throw new HttpException(sprintf('Failed to create tracking information for order id %s', $orderId));
        }
    }

    /**
     * Get the Order object from PayPal
     *
     * @param string $storeId
     * @param string $orderId
     * @return array
     * @throws HttpException
     * @throws NoSuchEntityException
     */
    public function get(string $storeId, string $orderId) : array
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($storeId)
        );

        $path = '/' . $this->config->getMerchantId() . '/payment/paypal/order/' . $orderId;

        $response = $this->httpClient->request(
            $headers,
            $path,
            Http::METHOD_GET,
        );

        $this->logger->debug(
            var_export(
                [
                    'request' => [
                        $path,
                        $headers,
                        Http::METHOD_GET,
                    ],
                    'response' => $response
                ],
                true
            )
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
}

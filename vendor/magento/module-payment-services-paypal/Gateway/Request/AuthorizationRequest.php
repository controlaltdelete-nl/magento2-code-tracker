<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Gateway\Request;

use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\PaymentServicesPaypal\Model\CustomerHeadersBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ScopeHeadersBuilder
     */
    private $scopeHeaderBuilder;

    /**
     * @var CustomerHeadersBuilder
     */
    private $customerHeaderBuilder;

    /**
     * @param Config $config
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param CustomerHeadersBuilder $customerHeaderBuilder
     */
    public function __construct(
        Config $config,
        ScopeHeadersBuilder $scopeHeaderBuilder,
        CustomerHeadersBuilder $customerHeaderBuilder,
    ) {
        $this->config = $config;
        $this->scopeHeaderBuilder = $scopeHeaderBuilder;
        $this->customerHeaderBuilder = $customerHeaderBuilder;
    }

    /**
     * Build authorization request
     *
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObjectInterface $payment */
        $payment = SubjectReader::readPayment($buildSubject);
        $extensionAttributes = $payment->getPayment()->getExtensionAttributes();
        $paymentToken = $extensionAttributes->getVaultPaymentToken();

        $uri = '/'
            . $this->config->getMerchantId()
            . '/payment/paypal/order/'
            . $this->getPayPalOrderId($payment)
            . '/authorize';

        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($payment->getOrder()->getStoreId()),
            $this->customerHeaderBuilder->buildCustomerHeaders($payment),
        );

        $body = [
            'mp-transaction' => [
                'order-increment-id' => $payment->getOrder()->getOrderIncrementId()
            ]
        ];
        if (isset($paymentToken)) {
            $body['mp-transaction']['payment-vault-id'] = $paymentToken->getGatewayToken();
        }

        return [
            'uri' => $uri,
            'method' => \Magento\Framework\App\Request\Http::METHOD_POST,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    private function getPayPalOrderId($payment) {
        $orderId = $payment->getPayment()->getAdditionalInformation('paypal_order_id');

        if (empty($orderId)) {
            throw new NoSuchEntityException(__("Order is missing and can not be authorized. Try again later."));
        }

        return $orderId;
    }
}

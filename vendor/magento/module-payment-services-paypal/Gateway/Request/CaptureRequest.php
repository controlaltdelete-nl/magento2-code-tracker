<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Gateway\Request;

use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class CaptureRequest implements BuilderInterface
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
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @param Config $config
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        Config $config,
        ScopeHeadersBuilder $scopeHeaderBuilder,
        OrderHelper $orderHelper
    ) {
        $this->config = $config;
        $this->scopeHeaderBuilder = $scopeHeaderBuilder;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Build the capture request which will be sent to payment gateway
     *
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $paymentDO->getPayment();
        $paymentsMode = $payment->getAdditionalInformation('payments_mode');
        $uri = '/'
            . $this->config->getMerchantId($paymentsMode)
            . '/payment/'
            . $payment->getAuthorizationTransaction()->getTxnId()
            . '/capture';

        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($payment->getOrder()->getStoreId()),
        );

        return [
            'uri' => $uri,
            'method' => \Magento\Framework\App\Request\Http::METHOD_POST,
            'body' => [
                'capture-request' => [
                    'amount' => [
                        'currency_code' => $payment->getOrder()->getBaseCurrencyCode(),
                        'value' => $this->orderHelper->formatAmount((float)SubjectReader::readAmount($buildSubject))
                    ]
                ]
            ],
            'headers' => $headers,
            'clientConfig' => [
                'environment' => $paymentsMode
            ]
        ];
    }
}

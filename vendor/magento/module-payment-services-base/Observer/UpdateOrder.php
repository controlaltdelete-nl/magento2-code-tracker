<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Magento\PaymentServicesBase\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\Sales\Model\Order;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;

class UpdateOrder extends AbstractDataAssignObserver
{
    /**
     * @var ServiceClientInterface
     */
    private $httpClient;

    /**
     * @var ScopeHeadersBuilder
     */
    private $scopeHeaderBuilder;

    /**
     * @var array
     */
    private $methods;

    /**
     * @param ServiceClientInterface $httpClient
     * @param ScopeHeadersBuilder $scopeHeaderBuilder
     * @param array $methods
     */
    public function __construct(
        ServiceClientInterface $httpClient,
        ScopeHeadersBuilder $scopeHeaderBuilder,
        array $methods = [],
    ) {
        $this->httpClient = $httpClient;
        $this->scopeHeaderBuilder = $scopeHeaderBuilder;
        $this->methods = $methods;
    }

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();
        if (!in_array($order->getPayment()->getMethod(), $this->methods)) {
            return $this;
        }

        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->scopeHeaderBuilder->buildScopeHeaders($order->getStore()),
        );

        $internalOrderId = $order->getPayment()->getAdditionalInformation('payments_order_id');

        $this->httpClient->request(
            $headers,
            '/payment/order/' . $internalOrderId,
            Http::METHOD_PATCH,
            json_encode([
                'order-id' => $order->getId(),
                'order-increment-id' => $order->getIncrementId(),
            ]),
        );

        return $this;
    }
}

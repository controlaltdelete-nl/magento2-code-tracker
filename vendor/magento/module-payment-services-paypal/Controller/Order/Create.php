<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2020 Adobe
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

namespace Magento\PaymentServicesPaypal\Controller\Order;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Create implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const VAULT_PARAM_KEY = 'vault';

    /**
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param OrderService $orderService
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param QuoteRepositoryInterface $quoteRepository
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly OrderService $orderService,
        private readonly ResultFactory $resultFactory,
        private readonly RequestInterface $request,
        private readonly QuoteRepositoryInterface $quoteRepository,
        private readonly OrderHelper $orderHelper
    ) {
    }

    /**
     * Dispatch the order creation request with Commerce params
     *
     * @return ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute() : ResultInterface
    {
        $shouldCardBeVaulted = $this->request->getParam(self::VAULT_PARAM_KEY) === 'true';
        $paymentSource = $this->request->getPost('payment_source');
        $threeDsMode = $this->request->getPost('three_ds_mode');
        $location = $this->orderHelper->validateCheckoutLocation($this->request->getPost('location'));
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $quote = $this->checkoutSession->getQuote();

            if (!$quote->getId() || count($quote->getAllItems()) === 0) {
                throw new HttpException('Unable to create order: The cart is empty or unavailable. Please try again.');
            }

            $isLoggedIn = $this->customerSession->isLoggedIn();
            $orderIncrementId = $this->orderHelper->reserveAndGetOrderIncrementId($quote);

            $data = [
                'amount' => $this->orderHelper->formatAmount((float)$quote->getBaseGrandTotal()),
                'l2_data' => $this->orderHelper->getL2Data($quote, $paymentSource ?? ''),
                'l3_data' => $this->orderHelper->getL3Data($quote, $paymentSource ?? ''),
                'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                'shipping_address' => $this->orderService->mapAddress($quote->getShippingAddress()),
                'billing_address' => $this->orderService->mapAddress($quote->getBillingAddress()),
                'payer' => $isLoggedIn
                    ? $this->orderService->buildPayer($quote, $this->customerSession->getCustomer()->getId())
                    : $this->orderService->buildGuestPayer($quote),
                'is_digital' => $quote->isVirtual(),
                'three_ds_mode' => $threeDsMode,
                'payment_source' => $paymentSource,
                'vault' => $shouldCardBeVaulted,
                'quote_id' => $quote->getId(),
                'order_increment_id' => $orderIncrementId,
                'line_items' => $this->orderHelper->getLineItems($quote, $orderIncrementId),
                'amount_breakdown' => $this->orderHelper->getAmountBreakdown($quote, $orderIncrementId),
                'location' => $location,
            ];

            // BXO for PayPal
            $data = $this->addBxoRequestDataForPayPal($paymentSource, (int)$quote->getStoreId(), $data);

            // For Guest user, Magento does not store email against Shipping Address
            // which leads to passing 'null' value for the email to the PayPal request
            // To resolve this, I have passed the email address from Billing Address
            if (!$isLoggedIn && $data['shipping_address']['email'] === null) {
                $data['shipping_address']['email'] = $quote->getBillingAddress()->getEmail();
            }

            $response = $this->orderService->create($quote->getStore(), $data);

            $response = array_merge_recursive(
                $response,
                [
                    "paypal-order" => [
                        "amount" => $quote->getBaseGrandTotal(),
                        "currency_code" => $quote->getCurrency()->getBaseCurrencyCode()
                    ]
                ]
            );

            if (isset($response["paypal-order"]['id'])) {
                $quote->getPayment()->setAdditionalInformation('paypal_order_id', $response["paypal-order"]['id']);
                $quote->getPayment()->setAdditionalInformation('paypal_order_amount', $quote->getBaseGrandTotal());

                if (!empty($location)) {
                    $quote->getPayment()->setAdditionalInformation('location', $location);
                }

                $this->quoteRepository->save($quote);
            }

            $result->setHttpResponseCode($response['status'])
                ->setData(['response' => $response]);
        } catch (HttpException $e) {
            $result->setHttpResponseCode(500);
        }
        return $result;
    }

    /**
     * Add BXO (App Switch and Contact Module) request data
     *
     * @param string|null $paymentSource
     * @param int $storeId
     * @param array $data
     * @return array
     * @throws NoSuchEntityException
     */
    private function addBxoRequestDataForPayPal(
        ?string $paymentSource,
        int $storeId,
        array $data
    ): array {
        if (in_array($paymentSource, Checkout::BXO_ALLOWED_PAYMENT_SOURCE)) {
            // App Switch
            if ($this->orderHelper->isAppSwitchEnabled($storeId)) {
                $data['return_url'] = $this->orderHelper->getCheckoutPaymentSectionUrl();
                $data['cancel_url'] = $this->orderHelper->getCheckoutPaymentSectionUrl();
            }

            // Contact Preference
            $data['contact_preference'] = Checkout::NO_CONTACT_INFO;
            if ($this->orderHelper->isContactPreferenceEnabled($storeId)) {
                $data['contact_preference'] = Checkout::RETAIN_CONTACT_INFO;
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request) :? InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request) :? bool
    {
        return true;
    }
}

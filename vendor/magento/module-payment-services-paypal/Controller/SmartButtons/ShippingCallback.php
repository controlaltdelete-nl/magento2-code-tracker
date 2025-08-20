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

namespace Magento\PaymentServicesPaypal\Controller\SmartButtons;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\PaymentServicesPaypal\Api\ShippingCallbackManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * This controller serves as the callback endpoint for PayPal's Server Side Shipping Callback functionality.
 * The endpoint URL is passed as 'callback_url' to PayPal during the order creation process.
 *
 * The controller processes the incoming PayPal callback request and sends the merchant response
 * back to PayPal, enabling dynamic shipping calculations and address validation
 */
class ShippingCallback implements HttpPostActionInterface, HttpGetActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param ShippingCallbackManagementInterface $shippingCallbackManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ShippingCallbackManagementInterface $shippingCallbackManagement,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $cartId = $this->request->getParam('cart_id');
            $sessionId = $this->request->getParam('session_id');
            $requestBody = $this->request->getContent();

            $this->logger->debug(
                'Request Body',
                [$requestBody]
            );

            if (!$cartId || !$sessionId || !$requestBody) {
                throw new LocalizedException(__('Missing required parameters'));
            }

            $response = $this->shippingCallbackManagement->execute(
                $cartId,
                $sessionId,
                $requestBody
            );

            $this->logger->debug(
                'Merchant Response',
                [$response]
            );

            $result->setHttpResponseCode(200);
            $result->setData($response);

        } catch (Exception $e) {
            $this->logger->error(
                'PayPal shipping callback error: ' . $e->getMessage(),
                ['exception' => $e]
            );

            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'COUNTRY_ERROR')) {
                $issueCode = 'COUNTRY_ERROR';
            } elseif (str_contains($errorMessage, 'STATE_ERROR')) {
                $issueCode = 'STATE_ERROR';
            } elseif (str_contains($errorMessage, 'ZIP_ERROR')) {
                $issueCode = 'ZIP_ERROR';
            } elseif (str_contains($errorMessage, 'METHOD_UNAVAILABLE')) {
                $issueCode = 'METHOD_UNAVAILABLE';
            } else {
                $issueCode = 'ADDRESS_ERROR';
            }

            $result->setHttpResponseCode(422);
            $result->setData([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [
                    [
                        'issue' => $issueCode,
                    ]
                ]
            ]);
        }

        return $result;
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Bypass CSRF validation for PayPal callbacks
     *
     * @param RequestInterface $request
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }
}

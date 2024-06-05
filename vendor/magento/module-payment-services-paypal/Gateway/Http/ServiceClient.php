<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Gateway\Http;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\PaymentServicesBase\Model\ServiceClientInterface;
use Magento\Payment\Model\Method\Logger;

class ServiceClient implements ClientInterface
{
    public const CAPTURE_ERRORS = [
        'INVALID_CURRENCY_CODE' => 'Currency code should be a three-character currency code.',
        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        'CANNOT_BE_ZERO_OR_NEGATIVE' => 'Must be greater than zero. If the currency supports decimals, only two decimal place precision is supported.',
        'DECIMAL_PRECISION' => 'The value of the field should not be more than two decimal places.',
        'DECIMALS_NOT_SUPPORTED' => 'Currency does not support decimals.',
        'TRANSACTION_REFUSED' => 'PayPal\'s internal controls prevent authorization from being captured.',
        'AUTHORIZATION_VOIDED' => 'A voided authorization cannot be captured or reauthorized.',
        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        'MAX_CAPTURE_COUNT_EXCEEDED' => 'Maximum number of allowable captures has been reached. No additional captures are possible for this authorization. Please contact customer service or your account manager to change the number of captures that be made for a given authorization.',
        // phpcs:disable Magento2.Files.LineLength, Generic.Files.LineLength
        'DUPLICATE_INVOICE_ID' => 'Requested invoice number has been previously captured. Possible duplicate transaction.',
        'AUTH_CAPTURE_CURRENCY_MISMATCH' => 'Currency of capture must be the same as currency of authorization.',
        'AUTHORIZATION_ALREADY_CAPTURED' => 'Authorization has already been captured.',
        'PAYER_CANNOT_PAY' => 'Payer cannot pay for this transaction.',
        'AUTHORIZATION_EXPIRED' => 'An expired authorization cannot be captured.',
        'MAX_CAPTURE_AMOUNT_EXCEEDED' => 'Capture amount exceeds allowable limit.',
        'PAYEE_ACCOUNT_LOCKED_OR_CLOSED' => 'Transaction could not complete because payee account is locked or closed.',
        'PAYER_ACCOUNT_LOCKED_OR_CLOSED' => 'The payer account cannot be used for this transaction.'
    ];

    private const DENIED_RESPONSE = "PAYMENT_DENIED";
    private const DECLINED_RESPONSE = "Payment was declined.";

    /**
     * @var ServiceClientInterface
     */
    private $httpClient;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ServiceClientInterface $httpClient
     * @param Logger $logger
     */
    public function __construct(
        ServiceClientInterface $httpClient,
        Logger $logger
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $environment = $transferObject->getClientConfig() ? $transferObject->getClientConfig()['environment'] : '';
        $response = $this->httpClient->request(
            $transferObject->getHeaders(),
            $transferObject->getUri(),
            $transferObject->getMethod(),
            $transferObject->getBody() == null ? '' : json_encode($transferObject->getBody()),
            'json',
            $environment
        );

        $this->logger->debug(
            [
                'request' => [
                    $transferObject->getUri(),
                    $transferObject->getHeaders(),
                    $transferObject->getMethod(),
                    $transferObject->getBody()
                ],
                'response' => $response
            ]
        );

        if (!$response['is_successful']) {
            if ($response['message'] === self::DENIED_RESPONSE || $response['message'] === self::DECLINED_RESPONSE) {
                throw new ClientException(
                    __(
                        'Your payment was not successful. '
                        . 'Ensure you have entered your details correctly and try again, '
                        . 'or try a different payment method. If you have continued problems, '
                        . 'contact the issuing bank for your payment method.'
                    )
                );
            } elseif (isset($transferObject->getBody()['capture-request'])) {
                throw new ClientException(__($this->getCaptureRequestError($response['message'])));
            } else {
                throw new ClientException(
                    __('Error happened when processing the request. Please try again later.')
                );
            }
        }

        return $response;
    }

    /**
     * Get error message for capture request.
     *
     * @param string $errorCode
     * @return string
     */
    private function getCaptureRequestError(string $errorCode): string
    {
        return self::CAPTURE_ERRORS[$errorCode]
            ?? 'Error happened when processing the request. Please try again later.';
    }
}

<?php
/************************************************************************
 *
 * Copyright 2024 Adobe
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
 * ************************************************************************
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model;

use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\PaymentServicesPaypal\Model\Config as PaypalConfig;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Integration\Model\CustomUserContext;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Psr\Log\LoggerInterface;

/**
 * Payments SDK config provider.
 *
 * Provides with configuration required to initialise Payments JS SDK
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class PaymentsSDKConfigProvider
{
    private const XML_PATH_GRAPHQL_DISABLE_SESSION = 'graphql/session/disable';
    public const KEY_SDK_URL = 'paymentsSDKUrl';
    public const KEY_STORE_VIEW_CODE = 'storeViewCode';
    public const KEY_OAUTH_TOKEN = 'oauthToken';
    public const KEY_GRAPHQL_ENDPOINT_URL = 'graphQLEndpointUrl';

    /**
     * @var BaseConfig
     */
    private BaseConfig $baseConfig;

    /**
     * @var PaypalConfig
     */
    private PaypalConfig $paypalConfig;

    /**
     * @var UserTokenIssuerInterface
     */
    private UserTokenIssuerInterface $tokenIssuer;

    /**
     * @var UserTokenParametersFactory
     */
    private UserTokenParametersFactory $tokenParametersFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param BaseConfig $baseConfig
     * @param PaypalConfig $paypalConfig
     * @param UserTokenIssuerInterface $tokenIssuer
     * @param UserTokenParametersFactory $tokenParametersFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerSession $customerSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        BaseConfig $baseConfig,
        PaypalConfig $paypalConfig,
        UserTokenIssuerInterface $tokenIssuer,
        UserTokenParametersFactory $tokenParametersFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        $this->baseConfig = $baseConfig;
        $this->paypalConfig = $paypalConfig;
        $this->tokenIssuer = $tokenIssuer;
        $this->tokenParametersFactory = $tokenParametersFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    /**
     * Get Payments SDK params.
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentsSDKParams(): array
    {
        return [
            self::KEY_SDK_URL               => $this->getSdkUrl(),
            self::KEY_STORE_VIEW_CODE       => $this->getStoreViewCode(),
            self::KEY_OAUTH_TOKEN           => $this->getAuthToken(),
            self::KEY_GRAPHQL_ENDPOINT_URL  => $this->getGraphQLEndpoint()
        ];
    }

    private function getSdkUrl(): string
    {
        $sdkUrl = $this->paypalConfig->getPaymentsSDKUrl();
        return $sdkUrl . '?ext=' . $this->baseConfig->getVersion();
    }

    /**
     * Get store view code.
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getStoreViewCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Get auth token.
     *
     * Use this token to authenticate the customer in the GraphQL request.
     *
     * @return string
     */
    private function getAuthToken(): string
    {
        if (!$this->isCookieSessionDisabledForGQL() || !$this->customerSession->isLoggedIn()) {
            return '';
        }

        try {
            $userContext = new CustomUserContext(
                (int) $this->customerSession->getCustomer()->getId(),
                UserContextInterface::USER_TYPE_CUSTOMER
            );

            return $this->tokenIssuer->create(
                $userContext,
                $this->tokenParametersFactory->create()
            );
        } catch (\Exception $e) {
            $this->logger->error("could not create token: " . $e->getMessage());
        }

        return '';
    }

    /**
     * Get GraphQL endpoint.
     *
     * If we use cookie session, we should use graphql endpoint that includes store code
     * If we use oauth token, we can use the default graphql endpoint
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getGraphQLEndpoint(): string
    {
        return $this->isCookieSessionDisabledForGQL()
            ? ''
            : $this->storeManager->getStore()->getBaseUrl() . 'graphql';
    }

    /**
     * Check if cookie session disabled for graphql area.
     *
     * We need this for compatibility with Magento 2.4.4.
     *
     * @return bool
     */
    private function isCookieSessionDisabledForGQL(): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_GRAPHQL_DISABLE_SESSION);

        if ($value === '1') {
            return true;
        }

        return false;
    }
}

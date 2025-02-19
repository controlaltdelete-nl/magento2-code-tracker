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

namespace Magento\PaymentServicesPaypal\Block\Customer\Vault;

use Magento\Framework\View\Element\Template\Context;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Psr\Log\LoggerInterface;
use Magento\PaymentServicesPaypal\Model\PaymentsSDKConfigProvider;
use Magento\Framework\App\ObjectManager;

/**
 * @api
 */
class AddCardForm extends \Magento\Framework\View\Element\Template
{
    /**
     * @var PaymentsSDKConfigProvider
     */
    private PaymentsSDKConfigProvider $paymentsSDKConfigProvider;
  
    /**
     * @param Context $context
     * @param Config $paymentsConfig
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     * @param UserTokenIssuerInterface $tokenIssuer
     * @param UserTokenParametersFactory $tokenParamsFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param array $data
     * @param PaymentsSDKConfigProvider $paymentsSDKConfigProvider
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Config $paymentsConfig,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        UserTokenIssuerInterface $tokenIssuer,
        UserTokenParametersFactory $tokenParamsFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        array $data = [],
        ?PaymentsSDKConfigProvider $paymentsSDKConfigProvider = null
    ) {
        parent::__construct($context, $data);
        $this->paymentsSDKConfigProvider = $paymentsSDKConfigProvider
            ?: ObjectManager::getInstance()->get(PaymentsSDKConfigProvider::class);
    }

    /**
     * Prepare the layout of the new credit card block.
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->pageConfig->getTitle()->set("Add New Card");

        return $this;
    }

    /**
     * Pass on data needed in the JS component.
     *
     * @return array
     */
    public function getComponentParams(): array
    {
        $sdkParams = $this->paymentsSDKConfigProvider->getPaymentsSDKParams();

        return [
            'savedCardListUrl'      => $this->getUrl('vault/cards/listaction'),
            'paymentsSDKUrl'        => $sdkParams[PaymentsSDKConfigProvider::KEY_SDK_URL],
            'storeViewCode'         => $sdkParams[PaymentsSDKConfigProvider::KEY_STORE_VIEW_CODE],
            'oauthToken'            => $sdkParams[PaymentsSDKConfigProvider::KEY_OAUTH_TOKEN],
            'graphQLEndpointUrl'    => $sdkParams[PaymentsSDKConfigProvider::KEY_GRAPHQL_ENDPOINT_URL],
        ];
    }
}

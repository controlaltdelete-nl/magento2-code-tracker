<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilderFactory;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilder;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;

class ApplePayConfigProvider extends AbstractConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'payment_services_paypal_apple_pay';

    private const LOCATION = 'checkout_applepay';

    public const PAYMENT_SOURCE = 'applepay';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BaseConfig
     */
    private $baseConfig;

    /**
     * @param Config $config
     * @param PaymentOptionsBuilderFactory $paymentOptionsBuilderFactory
     * @param SdkService $sdkService
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url
     * @param BaseConfig $baseConfig
     */
    public function __construct(
        Config $config,
        PaymentOptionsBuilderFactory $paymentOptionsBuilderFactory,
        SdkService $sdkService,
        StoreManagerInterface $storeManager,
        UrlInterface $url,
        BaseConfig $baseConfig
    ) {
        $this->baseConfig = $baseConfig;
        $this->config = $config;
        $this->url = $url;
        parent::__construct($config, $paymentOptionsBuilderFactory, $sdkService, $storeManager);
    }

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        if (!$this->baseConfig->isConfigured() || !$this->config->isApplePayLocationEnabled('checkout')) {
            $config['payment'][self::CODE]['isVisible'] = false;
            return $config;
        }
        $config['payment'][self::CODE]['isVisible'] = true;
        $config['payment'][self::CODE]['createOrderUrl'] = $this->url->getUrl('paymentservicespaypal/order/create');
        $config['payment'][self::CODE]['sdkParams'] = $this->getScriptParams(self::CODE, self::LOCATION);
        $config['payment'][self::CODE]['buttonStyles'] = $this->config->getButtonConfiguration();
        $config['payment'][self::CODE]['paymentTypeIconUrl'] =
            $this->config->getViewFileUrl('Magento_PaymentServicesPaypal::images/applepay.png');

        return $config;
    }

    /**
     * @inheritdoc
     */
    protected function getPaymentOptions(): PaymentOptionsBuilder
    {
        $paymentOptionsBuilder =  parent::getPaymentOptions();
        $paymentOptionsBuilder->setAreButtonsEnabled($this->config->isApplePayLocationEnabled('checkout'));
        $paymentOptionsBuilder->setIsPayPalCreditEnabled(false);
        $paymentOptionsBuilder->setIsVenmoEnabled(false);
        $paymentOptionsBuilder->setIsApplePayEnabled($this->config->isApplePayLocationEnabled('checkout'));
        $paymentOptionsBuilder->setIsCreditCardEnabled(false);
        $paymentOptionsBuilder->setIsPaylaterMessageEnabled(false);
        return $paymentOptionsBuilder;
    }
}

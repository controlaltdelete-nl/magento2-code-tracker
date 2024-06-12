<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Magento\PaymentServicesPaypal\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilder;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilderFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;

class SmartButtonsConfigProvider extends AbstractConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'payment_services_paypal_smart_buttons';

    public const PAYPAL_PAYMENT_SOURCE = 'paypal';

    private const LOCATION = 'checkout_smart_buttons';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BaseConfig
     */
    private $baseConfig;

    /**
     * @var array
     */
    private $messageStyles;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Config $config
     * @param PaymentOptionsBuilderFactory $paymentOptionsBuilderFactory
     * @param SdkService $sdkService
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url
     * @param BaseConfig $baseConfig
     * @param array $messageStyles
     */
    public function __construct(
        Config $config,
        PaymentOptionsBuilderFactory $paymentOptionsBuilderFactory,
        SdkService $sdkService,
        StoreManagerInterface $storeManager,
        UrlInterface $url,
        BaseConfig $baseConfig,
        array $messageStyles
    ) {
        $this->baseConfig = $baseConfig;
        $this->config = $config;
        $this->url = $url;
        $this->messageStyles = $messageStyles;
        $this->storeManager = $storeManager;
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
        if (!$this->baseConfig->isConfigured() || !$this->config->isLocationEnabled('checkout')) {
            $config['payment'][self::CODE]['isVisible'] = false;
            return $config;
        }
        $config['payment'][self::CODE]['isVisible'] = true;
        $config['payment'][self::CODE]['createOrderUrl'] = $this->url->getUrl('paymentservicespaypal/order/create');
        $config['payment'][self::CODE]['sdkParams'] = $this->getScriptParams(self::CODE, self::LOCATION);
        $config['payment'][self::CODE]['messageStyles'] = $this->messageStyles;
        $config['payment'][self::CODE]['canDisplayMessage'] = (bool) $this->config->canDisplayPayLaterMessage();
        $config['payment'][self::CODE]['buttonStyles'] = $this->config->getButtonConfiguration();
        $config['payment'][self::CODE]['paymentTypeIconUrl'] =
            $this->config->getViewFileUrl('Magento_PaymentServicesPaypal::images/paypal_vertical.png');
        return $config;
    }

    /**
     * @inheritdoc
     */
    protected function getPaymentOptions(): PaymentOptionsBuilder
    {
        $paymentOptionsBuilder =  parent::getPaymentOptions();
        $paymentOptionsBuilder->setAreButtonsEnabled($this->config->isLocationEnabled('checkout'));
        $paymentOptionsBuilder->setIsPayPalCreditEnabled($this->config->isFundingSourceEnabledByName('paypal_credit'));
        $paymentOptionsBuilder->setIsVenmoEnabled($this->config->isFundingSourceEnabledByName('venmo'));
        $paymentOptionsBuilder->setIsApplePayEnabled(false);
        $paymentOptionsBuilder->setIsPayPalCardEnabled($this->config->isFundingSourceEnabledByName('card'));
        $paymentOptionsBuilder->setIsPaylaterMessageEnabled($this->config->canDisplayPayLaterMessage());
        return $paymentOptionsBuilder;
    }
}

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

namespace Magento\PaymentServicesPaypal\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesPaypal\Model\Adminhtml\Source\ThreeDS;
use Magento\PaymentServicesPaypal\Model\SdkService\PaymentOptionsBuilder;
use Magento\Framework\UrlInterface;
use Magento\PaymentServicesBase\Model\Config as BaseConfig;
use Magento\Store\Model\StoreManagerInterface;
use Laminas\Uri\Uri;

class FastlaneConfigProvider implements ConfigProviderInterface
{
    public const CODE = Config::PAYMENTS_SERVICES_PREFIX . 'fastlane';
    private const LOCATION = 'checkout_fastlane';
    public const PAYMENT_SOURCE = 'fastlane';

    /**
     * @param Config $config
     * @param UrlInterface $url
     * @param BaseConfig $baseConfig
     * @param ConfigProvider $configProvider
     * @param StoreManagerInterface $storeManager
     * @param Uri $uri
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $url,
        private readonly BaseConfig $baseConfig,
        private readonly ConfigProvider $configProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly Uri $uri
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $config = $this->configProvider->getConfig();
        if (!$this->baseConfig->isConfigured() || !$this->config->isFastlaneEnabled()) {
            $config['payment'][self::CODE]['isVisible'] = false;
            return $config;
        }
        $config['payment'][self::CODE]['location'] = Config::CHECKOUT_CHECKOUT_LOCATION;
        $config['payment'][self::CODE]['isVisible'] = true;
        $config['payment'][self::CODE]['sdkParams'] = $this->configProvider->getScriptParams(
            self::CODE,
            self::LOCATION,
            $this->getPaymentOptions()
        );
        $config['payment'][self::CODE]['paymentSource'] = self::PAYMENT_SOURCE;
        $config['payment'][self::CODE]['messaging'] = $this->config->isFastlaneMessagingEnabled();
        $config['payment'][self::CODE]['styling'] = $this->config->getFastlaneStyles();
        $config['payment'][self::CODE]['threeDS'] = $this->isThreeDSecureEnabled();
        $config['payment'][self::CODE]['createOrderUrl'] = $this->url->getUrl('paymentservicespaypal/order/create');
        $config['payment'][self::CODE]['paymentTypeIconUrl'] =
            $this->config->getViewFileUrl('Magento_PaymentServicesPaypal::images/cc_icon.png');

        return $config;
    }

    /**
     * Get payment options
     *
     * @throws NoSuchEntityException
     */
    private function getPaymentOptions(): PaymentOptionsBuilder
    {
        $paymentOptionsBuilder = $this->configProvider->getPaymentOptions();
        $paymentOptionsBuilder->setIsFastlaneEnabled(true);
        $paymentOptionsBuilder->setIsFastlaneThreeDSEnabled($this->isThreeDSecureEnabled());

        $rootDomain = $this->getStoreRootDomain();
        if (!empty($rootDomain)) {
            $paymentOptionsBuilder->setDomains([$rootDomain]);
        }

        return $paymentOptionsBuilder;
    }

    /**
     * Get the root domain of the store.
     *
     * Returns the root domain without
     * subdomains, e.g., "example.com" from "sub.example.com"
     * and protocol
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getStoreRootDomain(): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $host = $this->uri->parse($baseUrl)->getHost();

        if (!$host) {
            return '';
        }

        $parts = explode('.', $host);
        $count = count($parts);

        if ($count >= 2) {
            return $parts[$count - 2] . '.' . $parts[$count - 1];
        }

        return $host;
    }

    /**
     * Is 3D Secure enabled?
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isThreeDSecureEnabled(): bool
    {
        $threeDsMode = $this->config->getFastlaneThreeDS();

        return $threeDsMode === ThreeDS::ALWAYS || $threeDsMode === ThreeDS::WHEN_REQUIRED;
    }
}

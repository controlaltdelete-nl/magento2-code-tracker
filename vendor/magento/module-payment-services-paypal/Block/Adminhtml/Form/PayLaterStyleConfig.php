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

namespace Magento\PaymentServicesPaypal\Block\Adminhtml\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\ScopeInterface as DefaultScopeInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\PaymentServicesBase\Model\MerchantService;
use Magento\PaymentServicesBase\Model\ScopeHeadersBuilder;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantInterface;
use Magento\PaymentServicesPaypal\Model\PaypalMerchantResolver;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class PayLaterStyleConfig extends Field
{
    private const MAP_SCOPE_FROM_ADMIN_TO_SAAS = [
        DefaultScopeInterface::SCOPE_DEFAULT => PaypalMerchantResolver::GLOBAL_SCOPE,
        ScopeInterface::SCOPE_WEBSITES => ScopeHeadersBuilder::WEBSITE_SCOPE_TYPE,
        ScopeInterface::SCOPE_STORES => ScopeHeadersBuilder::STOREVIEW_SCOPE_TYPE
    ];
    private const BNPL_CONFIGURATOR_CACHE_IDENTIFIER = 'bnpl_configurator_%s_%s';
    private const BNPL_CONFIGURATOR_CACHE_TAG = 'bnpl_configurator';
    private const CACHE_LIFETIME = 86400;

    /**
     * @var string
     */
    protected $_template = 'Magento_PaymentServicesPaypal::system/config/paylater-style-configurator.phtml';

    /**
     * @param Context $context
     * @param MerchantService $merchantService
     * @param PaypalMerchantResolver $paypalMerchantResolver
     * @param ConfigCache $configCache
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly MerchantService $merchantService,
        private readonly PaypalMerchantResolver $paypalMerchantResolver,
        private readonly ConfigCache $configCache,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     * @throws InputException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $scopeType = self::MAP_SCOPE_FROM_ADMIN_TO_SAAS[$element->getScope()] ?? PaypalMerchantResolver::GLOBAL_SCOPE;
        $scopeId = (int)$element->getScopeId() ?? 0;

        $paypalMerchant = $this->paypalMerchantResolver->getPayPalMerchant($scopeType, $scopeId);

        if ($paypalMerchant->getId() && $paypalMerchant->getStatus() === PaypalMerchantInterface::COMPLETED_STATUS) {
            $storeLocale = $this->_scopeConfig->getValue('general/locale/code');

            // Unique key per scope
            $cacheKey = sprintf(self::BNPL_CONFIGURATOR_CACHE_IDENTIFIER, $scopeType, $scopeId);
            $cachedData = $this->configCache->load($cacheKey);

            try {
                if ($cachedData) {
                    $response = json_decode($cachedData, true);
                } else {
                    $response = $this->merchantService->getMerchantAndPartnerInformation($scopeType, $scopeId);

                    // Cache the result if successful
                    $this->configCache->save(
                        json_encode($response),
                        $cacheKey,
                        [self::BNPL_CONFIGURATOR_CACHE_TAG],
                        self::CACHE_LIFETIME
                    );
                }

                $this->addData([
                    'paypal_merchant_id' => $response['merchantIdentifier'],
                    'partner_client_id' => $response['partnerClientId'],
                    'partner_name' => $response['partnerName'],
                    'bn_code' => $response['bnCode'],
                    'store_locale' => $storeLocale
                ]);

                return $this->_toHtml();
            } catch (HttpException $exception) {
                $this->logger->debug(
                    'BNPL Configurator API error response',
                    [$exception->getMessage()]
                );
                return '';
            }
        }

        return '';
    }
}

<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Observer;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\PaymentServicesBase\Model\Config;

class SaveAdditionalData extends AbstractDataAssignObserver
{
    private const PAYMENT_MODE_KEY = 'payments_mode';
    private const PAYPAL_FASTLANE_TOKEN = 'paypal_fastlane_token';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var string[]
     */
    private $additionalInformationList = [
        'payments_order_id',
        'paypal_order_id',
        'payment_source',
        'paypal_fastlane_profile'
    ];

    /**
     * @param Config $config
     * @param EncryptorInterface $encryptor
     */
    public function __construct(Config $config, EncryptorInterface $encryptor)
    {
        $this->encryptor = $encryptor;
        $this->config = $config;
    }

    /**
     * Save additional data to payment.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        $paymentInfo = $this->readPaymentModelArgument($observer);
        $storeId = $paymentInfo->getQuote()->getStore()->getStoreId();
        $paymentInfo->setAdditionalInformation(
            self::PAYMENT_MODE_KEY,
            $this->config->getEnvironmentType($storeId)
        );
        if (!is_array($additionalData)) {
            return;
        }
        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }

        $this->savePaypalFastlaneToken($additionalData, $paymentInfo);
    }

    /**
     * Encrypt and save Paypal Fastlane token to payment info.
     *
     * @param array $additionalData
     * @param InfoInterface $paymentInfo
     * @return void
     */
    private function savePaypalFastlaneToken(array $additionalData, InfoInterface $paymentInfo):void
    {
        if (!empty($additionalData[self::PAYPAL_FASTLANE_TOKEN])) {
            $paymentInfo->setAdditionalInformation(
                self::PAYPAL_FASTLANE_TOKEN,
                $this->encryptor->encrypt($additionalData[self::PAYPAL_FASTLANE_TOKEN])
            );
        }
    }
}

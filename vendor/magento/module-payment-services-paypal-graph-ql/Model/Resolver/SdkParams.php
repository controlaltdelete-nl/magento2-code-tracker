<?php
/*************************************************************************
 * ADOBE CONFIDENTIAL
 * ___________________
 *
 * Copyright 2023 Adobe
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
 **************************************************************************/
declare(strict_types=1);

namespace Magento\PaymentServicesPaypalGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SdkParams implements ResolverInterface
{
    /**
     * @var mixed
     */
    private mixed $cspNonceProvider;

    /**
     * Constructor
     */
    public function __construct()
    {
        //TODO:Just to be compatible with 2.4.6. Remove in future
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $this->cspNonceProvider = $objectManager->get("\Magento\Csp\Helper\CspNonceProvider");
        } catch (\Throwable $e) {
            $this->cspNonceProvider = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $sdkParams = [];
        foreach ($value[$field->getName()] as $sdkParam) {
            $sdkParams[] = $sdkParam->getData();
        }

        $cspNonce = $this->getCspNonce();
        if ($cspNonce && count($sdkParams) > 0) {
            $sdkParams[] = $cspNonce;
        }

        return $sdkParams;
    }

    /**
     * Encapsulate CSP nonce logic.
     *
     * @return array|null
     */
    private function getCspNonce(): ?array
    {
        if ($this->cspNonceProvider === null) {
            return null;
        }
        return ['name' => 'data-csp-nonce', 'value' => $this->cspNonceProvider->generateNonce()];
    }
}

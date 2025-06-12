<?php

/**
 * ADOBE CONFIDENTIAL
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
 */

declare(strict_types=1);

namespace Magento\PaymentServicesPaypalGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\PaymentServicesPaypal\Helper\TextSanitiser;
use Magento\PaymentServicesPaypal\Model\VaultService;

/**
 * CreateVaultCardPaymentToken resolver, used for GraphQL mutation processing.
 *
 */
class CreateVaultCardPaymentToken implements ResolverInterface
{
    /**
     * @var VaultService
     */
    private VaultService $vaultService;

    /**
     * @var TextSanitiser
     */
    private TextSanitiser $textSanitiser;

    /**
     * @param VaultService $vaultService
     * @param TextSanitiser $textSanitiser
     */
    public function __construct(
        VaultService $vaultService,
        TextSanitiser $textSanitiser
    ) {
        $this->vaultService = $vaultService;
        $this->textSanitiser = $textSanitiser;
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
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('"input" value should be specified'));
        }

        $setupTokenId = $this->textSanitiser->textOnly((string)$args['input']['setup_token_id']);
        $cardDescription = $this->textSanitiser->textOnly((string)($args['input']['card_description'] ?? ''));
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        $response = $this->vaultService->createVaultCardPaymentToken(
            (int) $context->getUserId(),
            $setupTokenId,
            $cardDescription,
            $storeId
        );

        return $this->formatVaultCardPaymentTokenResponse($response);
    }

    /**
     * Format the response from the VaultService to match the graphQL output type CreateVaultCardPaymentTokenOutput
     *
     * @param array $response
     * @return array
     */
    private function formatVaultCardPaymentTokenResponse(array $response): array
    {
        return [
            'vault_token_id' => $response['mp-vault-token-id'],
            'payment_source' => [
                'card' => [
                    'brand' => $response['payment_source']['card']['brand'],
                    'last_digits' => $response['payment_source']['card']['last_digits'],
                    'expiry' => $response['payment_source']['card']['expiry'],
                ]
            ]
        ];
    }
}

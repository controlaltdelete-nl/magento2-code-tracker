<?php
/*************************************************************************
 * ADOBE CONFIDENTIAL
 * ___________________
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
 **************************************************************************/
declare(strict_types=1);

namespace Magento\PaymentServicesPaypalGraphQl\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Cart\AddProductsToCart;
use Magento\Quote\Model\Cart\Data\AddProductsToCartOutput;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\Cart\Data\Error;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForGuest;
use Psr\Log\LoggerInterface;

class AddProductsToNewCart implements ResolverInterface
{
    /**
     * @var CreateEmptyCartForGuest
     */
    private CreateEmptyCartForGuest $cartManagement;

    /**
     * @var AddProductsToCart
     */
    private AddProductsToCart $addProductsToCart;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private MaskedQuoteIdToQuoteIdInterface $maskQuoteIdConverter;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var mixed
     */
    private mixed $cartItemPrecursor;

    /**
     * @param CreateEmptyCartForGuest $cartManagement
     * @param AddProductsToCart $addProductsToCart
     * @param MaskedQuoteIdToQuoteIdInterface $maskQuoteIdConverter
     * @param CartRepositoryInterface $cartRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param LoggerInterface $logger
     * @throws GraphQlInputException
     */
    public function __construct(
        CreateEmptyCartForGuest $cartManagement,
        AddProductsToCart $addProductsToCart,
        MaskedQuoteIdToQuoteIdInterface $maskQuoteIdConverter,
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->cartManagement = $cartManagement;
        $this->addProductsToCart = $addProductsToCart;
        $this->maskQuoteIdConverter = $maskQuoteIdConverter;
        $this->cartRepository = $cartRepository;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;

        // PrecursorInterface only exists from 2.4.6+
        $interface = \Magento\QuoteGraphQl\Model\CartItem\PrecursorInterface::class;

        if (!interface_exists($interface)) {
            throw new GraphQlInputException(__('This feature requires Magento 2.4.6 or higher.'));
        }

        $this->cartItemPrecursor = \Magento\Framework\App\ObjectManager::getInstance()->get($interface);
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
        if (empty($args['cartItems']) || !is_array($args['cartItems'])
        ) {
            throw new GraphQlInputException(__('Required parameter "cartItems" is missing'));
        }

        $maskedCartId = $this->cartManagement->execute();
        $customerId = (int) $context->getUserId() ?? null;

        // Always create a new cart for the customer
        $cart = $this->createNewCart($maskedCartId, $customerId);

        // Add the items to the cart
        $addProductsToCartOutput = $this->addProductsToCart($args['cartItems'], $context, $maskedCartId);

        // if there is an error on adding the products to the cart, delete the cart and we return the errors
        if (count($addProductsToCartOutput->getErrors()) > 0 || count($this->cartItemPrecursor->getErrors()) > 0) {
            $this->deleteCart($cart);

            return [
                'user_errors' => array_map(
                    function (Error $error) {
                        return [
                            'code' => $error->getCode(),
                            'message' => $error->getMessage(),
                            'path' => [$error->getCartItemPosition()]
                        ];
                    },
                    array_merge($addProductsToCartOutput->getErrors(), $this->cartItemPrecursor->getErrors())
                )
            ];
        }

        return [
            'cart' => [
                'model' => $addProductsToCartOutput->getCart(),
            ],
        ];
    }

    /**
     * Creates a new cart even if the customer is logged in as we want a "shadow" quote for the PDP
     *
     * @param string $maskedCartId
     * @param int|null $customerId
     * @return CartInterface
     *
     * @throws GraphQlNoSuchEntityException
     * @throws NoSuchEntityException
     */
    private function createNewCart(string $maskedCartId, ?int $customerId): CartInterface
    {
        $cartId = $this->maskQuoteIdConverter->execute($maskedCartId);
        $cart = $this->cartRepository->get($cartId);

        if ($customerId !== null && $customerId !== 0) {
            try {
                $customer = $this->customerRepository->getById($customerId);
                $cart->setCustomer($customer);
                $cart->setCustomerIsGuest(0);
            } catch (LocalizedException $e) {
                throw new GraphQlNoSuchEntityException(
                    __('Customer with ID %1 does not exist.', $customerId),
                    $e
                );
            }
        }

        return $cart;
    }

    /**
     * Add products to the cart
     *
     * @param array $cartItems
     * @param ContextInterface $context
     * @param string $maskedCartId
     * @return AddProductsToCartOutput
     *
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function addProductsToCart(
        array $cartItems,
        ContextInterface $context,
        string $maskedCartId
    ): AddProductsToCartOutput {
        $cartItemsData = $this->cartItemPrecursor->process($cartItems, $context);

        $cartItems = [];
        $cartItemFactory = new CartItemFactory();
        foreach ($cartItemsData as $cartItemData) {
            $cartItems[] = $cartItemFactory->create($cartItemData);
        }

        return $this->addProductsToCart->execute($maskedCartId, $cartItems);
    }

    /**
     * Delete the cart after a failed attempt to add products
     * Log the exception in case the delete fails
     *
     * @param CartInterface $cart
     * @return void
     */
    private function deleteCart(CartInterface $cart): void
    {
        try {
            $this->cartRepository->delete($cart);
        } catch (\Exception $e) {
            $this->logger->info(
                'Error deleting cart after failed add products to cart',
                ['exception' => $e->getMessage(), 'cart_id' => $cart->getId()]
            );
        }
    }
}

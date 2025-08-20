<?php
/************************************************************************
 *
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
 * ************************************************************************
 */

declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event;
use Magento\PaymentServicesPaypal\Block\Message;
use Magento\Checkout\Block\QuoteShortcutButtons;
use Magento\PaymentServicesBase\Model\Config;

class AddCheckoutComponents implements ObserverInterface
{
    const MINICART = 'minicart';
    const CART = 'cart';
    const PRODUCT = 'product';
    /**
     * @var Config $paymentConfig
     */
    private Config $paymentConfig;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var array
     */
    private $blocks;

    /**
     * @param Config $paymentConfig
     * @param Session $session
     * @param array $blocks
     */
    public function __construct(
        Config $paymentConfig,
        Session $session,
        array $blocks = []
    ) {
        $this->paymentConfig = $paymentConfig;
        $this->session = $session;
        $this->blocks = $blocks;
    }

    /**
     * @ingeritdoc
     */
    public function execute(EventObserver $observer)
    {
        if (!$this->paymentConfig->isConfigured()) {
            return;
        }

        $pageType = $this->getPageType($observer->getEvent());

        // For MINICART - Only show the express buttons when the quote has a total
        if ($pageType == self::MINICART && !(bool)(float)$this->session->getQuote()->getGrandTotal()) {
            return;
        }

        /** @var QuoteShortcutButtons $shortcutButtons */
        $shortcutButtons = $observer->getEvent()->getContainer();
        $smartButtons = $shortcutButtons->getLayout()->createBlock(
            $this->blocks[$pageType],
            '',
            [
                'pageType' => $pageType,
            ]
        );
        $shortcutButtons->addShortcut($smartButtons);
        $message = $shortcutButtons->getLayout()->createBlock(
            Message::class,
            '',
            [
                'pageType' => $pageType,
            ]
        );
        $shortcutButtons->addShortcut($message);
    }

    /**
     * @param Event $event
     * @return string
     */
    private function getPageType($event) : string
    {
        if ($event->getIsCatalogProduct()) {
            return self::PRODUCT;
        }
        if ($event->getIsShoppingCart()) {
            return self::CART;
        }
        return self::MINICART;
    }
}

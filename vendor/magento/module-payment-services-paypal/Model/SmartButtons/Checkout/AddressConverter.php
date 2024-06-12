<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout;

class AddressConverter
{
    /**
     * Convert shipping address.
     *
     * @param array $order
     * @return array
     */
    public function convertShippingAddress(array $order) : array
    {
        $data = [
            'email' => $order['paypal-order']['payer']['email'],
        ];

        if (isset($order['paypal-order']['payer']['phone_number'])) {
            $data['telephone'] = $order['paypal-order']['payer']['phone_number'];
        }

        if (isset($order['paypal-order']['shipping-address'])) {
            $data['street'][0] = $order['paypal-order']['shipping-address']['address_line_1'];
            if (isset($order['paypal-order']['shipping-address']['address_line_2'])) {
                $data['street'][1] = $order['paypal-order']['shipping-address']['address_line_2'];
            }
            $data['postcode'] = $order['paypal-order']['shipping-address']['postal_code'];
            $data['city'] = $order['paypal-order']['shipping-address']['admin_area_2'];
            $data['region'] = $order['paypal-order']['shipping-address']['admin_area_1'];
            $data['region_id'] = '';
            $data['country_id'] = $order['paypal-order']['shipping-address']['country_code'];

            if (isset($order['paypal-order']['shipping-address']['full_name'])) {
                $nameParts = explode(' ', $order['paypal-order']['shipping-address']['full_name'], 2);
                $data['firstname'] = $nameParts[0];

                if (isset($nameParts[1])) {
                    $data['lastname'] = $nameParts[1];
                }
            }
        }
        return $data;
    }

    /**
     * Convert billing address.
     *
     * @param array $order
     * @return array
     */
    public function convertBillingAddress(array $order) : array
    {
        $data = [
            'firstname' => $order['paypal-order']['payer']['name']['given_name'],
            'lastname' => $order['paypal-order']['payer']['name']['surname'],
            'country_id' => $order['paypal-order']['billing-address']['country_code'],
            'email' => $order['paypal-order']['payer']['email']
        ];

        if (isset($order['paypal-order']['billing-address']['address_line_1'])) {
            $data['street'][0] = $order['paypal-order']['billing-address']['address_line_1'];
        }
        if (isset($order['paypal-order']['billing-address']['address_line_2'])) {
            $data['street'][1] = $order['paypal-order']['billing-address']['address_line_2'];
        }
        if (isset($order['paypal-order']['billing-address']['postal_code'])) {
            $data['postcode'] = $order['paypal-order']['billing-address']['postal_code'];
        }
        if (isset($order['paypal-order']['billing-address']['admin_area_1'])) {
            $data['region'] = $order['paypal-order']['billing-address']['admin_area_1'];
            $data['region_id'] = '';
        }
        if (isset($order['paypal-order']['billing-address']['admin_area_2'])) {
            $data['city'] = $order['paypal-order']['billing-address']['admin_area_2'];
        }

        if (isset($order['paypal-order']['payer']['phone_number'])) {
            $data['telephone'] = $order['paypal-order']['payer']['phone_number'];
        }

        return $data;
    }
}

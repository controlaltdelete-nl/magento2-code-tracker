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
        $data = [];

        $this->addEmail($data, $order['paypal-order']['payer']);
        $this->addTelephone($data, $order['paypal-order']['payer']);

        if (isset($order['paypal-order']['shipping-address'])) {
            $address = $order['paypal-order']['shipping-address'];

            $this->addStreet($data, $address);
            $this->addPostalCode($data, $address);
            $this->addCity($data, $address);
            $this->addRegion($data, $address);
            $this->addCountry($data, $address);
            $this->addFullName($data, $address);
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
            'country_id' => $order['paypal-order']['billing-address']['country_code'],
        ];

        $this->addLastName($data, $order['paypal-order']['payer']);
        $this->addEmail($data, $order['paypal-order']['payer']);
        $this->addTelephone($data, $order['paypal-order']['payer']);

        $address = $order['paypal-order']['billing-address'];

        $this->addStreet($data, $address);
        $this->addPostalCode($data, $address);
        $this->addCity($data, $address);
        $this->addRegion($data, $address);

        return $data;
    }

    /**
     * Add street to data.
     *
     * @param array $data
     * @param array $address
     * @return void
     */
    private function addStreet(array &$data, array $address) : void
    {
        if (isset($address['address_line_1'])) {
            $data['street'][0] = $address['address_line_1'];
        }

        if (isset($address['address_line_2'])) {
            $data['street'][1] = $address['address_line_2'];
        }
    }

    /**
     * Add the postal code to data.
     *
     * @param array $data
     * @param array $address
     * @return void
     */
    private function addPostalCode(array &$data, array $address) : void
    {
        if (isset($address['postal_code'])) {
            $data['postcode'] = $address['postal_code'];
        }
    }

    /**
     * Add the city to data.
     *
     * @param array $data
     * @param array $address
     * @return void
     */
    private function addCity(array &$data, array $address) : void
    {
        if (isset($address['admin_area_2'])) {
            $data['city'] = $address['admin_area_2'];
        }
    }

    /**
     * Add the region to data.
     *
     * @param array $data
     * @param array $address
     * @return void
     */
    private function addRegion(array &$data, array $address) : void
    {
        if (isset($address['admin_area_1'])) {
            $data['region'] = $address['admin_area_1'];
            $data['region_id'] = '';
        }
    }

    /**
     * Add the country to data.
     *
     * @param array $data
     * @param array $address
     * @return void
     */
    private function addCountry(array &$data, array $address) : void
    {
        if (isset($address['country_code'])) {
            $data['country_id'] = $address['country_code'];
        }
    }

    /**
     * Add the full name to data.
     *
     * @param array $data
     * @param array $address
     * @return void
     */
    private function addFullName(array &$data, array $address) : void
    {
        if (isset($address['full_name'])) {
            $nameParts = explode(' ', $address['full_name'], 2);
            $data['firstname'] = $nameParts[0];

            if (isset($nameParts[1])) {
                $data['lastname'] = $nameParts[1];
            }
        }
    }

    /**
     * Add the telephone to data.
     *
     * @param array $data
     * @param array $payer
     * @return void
     */
    private function addTelephone(array &$data, array $payer) : void
    {
        if (isset($payer['phone_number'])) {
            $data['telephone'] = $payer['phone_number'];
        }
    }

    /**
     * Add the email to data.
     *
     * @param array $data
     * @param array $payer
     * @return void
     */
    private function addEmail(array &$data, array $payer) : void
    {
        if (isset($payer['email'])) {
            $data['email'] = $payer['email'];
        }
    }

    /**
     * Add the lastname to data.
     *
     * @param array $data
     * @param array $payer
     * @return void
     */
    private function addLastName(array &$data, array $payer) : void
    {
        if (isset($payer['name']['surname'])) {
            $data['lastname'] = $payer['name']['surname'];
        }
    }
}

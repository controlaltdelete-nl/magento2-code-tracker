<?php

/**
 * ADOBE CONFIDENTIAL
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
 */

declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Test\Unit\Model\SmartButtons\Checkout;

use Magento\PaymentServicesPaypal\Model\SmartButtons\Checkout\AddressConverter;
use PHPUnit\Framework\TestCase;

class AddressConverterTest extends TestCase
{
    /**
     * @var AddressConverter
     */
    private AddressConverter $addressConverter;

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        $this->addressConverter = new AddressConverter();
    }

    /**
     * @return void
     */
    public function testConvertShippingAddress(): void
    {
        $order = [
            'paypal-order' => [
                'payer' => [
                    'email' => 'test@test.com',
                    'phone_number' => '1234567',
                ],
                'shipping-address' => [
                    'address_line_1' => 'street 1',
                    'address_line_2' => 'street 2',
                    'postal_code' => '08005',
                    'admin_area_1' => 'region',
                    'admin_area_2' => 'city',
                    'country_code' => 'ESP',
                    'full_name' => 'John Doe',
                ],
            ],
        ];

        $address = $this->addressConverter->convertShippingAddress($order);

        $expected = [
            'street' => [
                0 => 'street 1',
                1 => 'street 2'
            ],
            'postcode' => '08005',
            'region' => 'region',
            'region_id' => '',
            'city' => 'city',
            'country_id' => 'ESP',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'telephone' => '1234567',
            'email' => 'test@test.com'
        ];

        $this->assertEquals($expected, $address);
    }

    /**
     * @return void
     */
    public function testConvertBillingAddress(): void
    {
        $order = [
            'paypal-order' => [
                'payer' => [
                    'name' => [
                        'given_name' => 'John',
                        'surname' => 'Doe'
                    ],
                    'email' => 'test@test.com',
                    'phone_number' => '2345678'
                ],
                'billing-address' => [
                    'address_line_1' => 'street 1',
                    'address_line_2' => 'street 2',
                    'postal_code' => '08005',
                    'admin_area_1' => 'region',
                    'admin_area_2' => 'city',
                    'country_code' => 'ESP',
                    'full_name' => 'John Doe'
                ],
            ],
        ];

        $address = $this->addressConverter->convertBillingAddress($order);

        $expected = [
            'street' => [
                0 => 'street 1',
                1 => 'street 2'
            ],
            'postcode' => '08005',
            'region' => 'region',
            'region_id' => '',
            'city' => 'city',
            'country_id' => 'ESP',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'telephone' => '2345678',
            'email' => 'test@test.com'
        ];

        $this->assertEquals($expected, $address);
    }

    /**
     * @return void
     */
    public function testConvertShippingAddressWithEmptyPayer(): void
    {
        $order = [
            'paypal-order' => [
                'payer' => [],
                'shipping-address' => [
                    'address_line_1' => 'street 1',
                    'address_line_2' => 'street 2',
                    'postal_code' => '08005',
                    'admin_area_1' => 'region',
                    'admin_area_2' => 'city',
                    'country_code' => 'ESP',
                    'full_name' => 'John Doe'
                ],
            ],
        ];

        $address = $this->addressConverter->convertShippingAddress($order);

        $expected = [
            'street' => [
                0 => 'street 1',
                1 => 'street 2'
            ],
            'postcode' => '08005',
            'region' => 'region',
            'region_id' => '',
            'city' => 'city',
            'country_id' => 'ESP',
            'firstname' => 'John',
            'lastname' => 'Doe'
        ];

        $this->assertEquals($expected, $address);
    }

    /**
     * @return void
     */
    public function testConvertPartialBillingAddress(): void
    {
        $order = [
            'paypal-order' => [
                'payer' => [
                    'name' => [
                        'given_name' => 'John'
                    ],
                ],
                'billing-address' => [
                    'address_line_1' => 'street 1',
                    'address_line_2' => 'street 2',
                    'postal_code' => '08005',
                    'admin_area_1' => 'region',
                    'admin_area_2' => 'city',
                    'country_code' => 'ESP',
                    'full_name' => 'John Doe'
                ],
            ],
        ];

        $address = $this->addressConverter->convertBillingAddress($order);

        $expected = [
            'street' => [
                0 => 'street 1',
                1 => 'street 2'
            ],
            'postcode' => '08005',
            'region' => 'region',
            'region_id' => '',
            'city' => 'city',
            'country_id' => 'ESP',
            'firstname' => 'John'
        ];

        $this->assertEquals($expected, $address);
    }

    /**
     * Test telephone priority from shipping address over payer
     */
    public function testConvertShippingAddressTelephonePriorityFromShipping(): void
    {
        $order = [
            'paypal-order' => [
                'payer' => [
                    'phone_number' => '1234567890'
                ],
                'shipping-address' => [
                    'phone_number' => [
                        'national_number' => '9876543210'
                    ],
                    'full_name' => 'John Doe'
                ],
            ],
        ];

        $address = $this->addressConverter->convertShippingAddress($order);

        $this->assertEquals('9876543210', $address['telephone']);
    }

    /**
     * Test email priority from shipping address over payer
     */
    public function testConvertShippingAddressEmailPriorityFromShipping(): void
    {
        $order = [
            'paypal-order' => [
                'payer' => [
                    'email' => 'payer@test.com'
                ],
                'shipping-address' => [
                    'email' => 'shipping@test.com',
                    'full_name' => 'John Doe'
                ],
            ],
        ];

        $address = $this->addressConverter->convertShippingAddress($order);

        $this->assertEquals('shipping@test.com', $address['email']);
    }

    /**
     * Test shipping address with empty phone number
     */
    public function testConvertShippingAddressEmptyPhoneNumber(): void
    {
        $order = [
            'paypal-order' => [
                'payer' => [
                    'phone_number' => '1234567890'
                ],
                'shipping-address' => [
                    'phone_number' => [
                        'national_number' => ''
                    ],
                    'full_name' => 'John Doe'
                ],
            ],
        ];

        $address = $this->addressConverter->convertShippingAddress($order);

        // Fallback to payer phone number
        $this->assertEquals('1234567890', $address['telephone']);
    }
}

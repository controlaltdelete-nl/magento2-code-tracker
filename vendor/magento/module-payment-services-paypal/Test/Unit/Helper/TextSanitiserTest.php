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

namespace Magento\PaymentServicesPaypal\Test\Unit\Helper;

use Magento\PaymentServicesPaypal\Helper\TextSanitiser;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TextSanitiserTest extends TestCase
{
    /**
     * @dataProvider provideTestCases
     */
    public function testSanitiserWithValidText($input, $expectedOutput): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $textSanitiser = new TextSanitiser($logger);

        $this->assertEquals($expectedOutput, $textSanitiser->textOnly($input));
    }

    public function provideTestCases()
    {
        return [
            ['This is a valid input', 'This is a valid input'],
            ['1234567890', '1234567890'],
            ['ÆØÅ æøå ß éñç', 'ÆØÅ æøå ß éñç'],
            ['Café & Restaurant!', 'Café  Restaurant!'],
            ['中文测试, 日本語テスト', '中文测试, 日本語テスト'],
            ['googlepayklVi2vyp\') OR 209=(', 'googlepayklVi2vyp OR 209'],
            ['OR 2 696-696-1=0', 'OR 2 696-696-10'],
            ['System (3/pk)', 'System 3pk'],
            ['🅻🅾🆁🅴🅼', ''],
            ['春天來了, 陽光溫暖', '春天來了, 陽光溫暖'],
            ['Full-length', 'Full-length'],

        ];
    }
}

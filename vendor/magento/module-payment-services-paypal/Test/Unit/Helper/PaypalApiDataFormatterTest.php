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
use Magento\PaymentServicesPaypal\Helper\PaypalApiDataFormatter;
use Psr\Log\LoggerInterface;

class PaypalApiDataFormatterTest extends TestCase
{

    /**
     * @var PaypalApiDataFormatter
     */
    private PaypalApiDataFormatter $paypalApiDataFormatter;

    /**
     * Setup the test
     */
    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->paypalApiDataFormatter = new PaypalApiDataFormatter(new TextSanitiser($logger));
    }

    public function testFormatNameLongNameWithMultiByteCharacters(): void
    {
        $name =
            "春天來了, 陽光溫暖, 大地復甦. 公園裡,  花兒盛開, 蝴蝶飛舞, 孩子們歡笑奔跑. " .
            "老人們在樹下下棋, 分享往事. 湖水波光粼粼, 鴨子悠閒游動, 微風輕拂, 帶來陣陣花香, " .
            "讓人心曠神怡. 春天來了, 陽光溫暖, 大地復甦.  花兒盛開, 蝴蝶飛舞, 孩子們歡笑奔跑. " .
            "老人們在樹下下棋, 分享往事. 湖水波光粼粼, 鴨子悠閒游動, 微風輕拂, 帶來陣陣花香, 讓人心曠神怡.";

        # Truncated to the first 127 (multibyte) characters
        $expectedFormattedName =
            "春天來了, 陽光溫暖, 大地復甦. 公園裡,  花兒盛開, 蝴蝶飛舞, 孩子們歡笑奔跑. " .
            "老人們在樹下下棋, 分享往事. 湖水波光粼粼, 鴨子悠閒游動, 微風輕拂, 帶來陣陣花香, " .
            "讓人心曠神怡. 春天來了, 陽光溫暖, 大地復甦.  花兒盛開, 蝴蝶飛";

        $actualFormattedName = $this->paypalApiDataFormatter->formatName($name);
        $this->assertEquals($expectedFormattedName, $actualFormattedName);
    }

    public function testFormatNameWithInvalidCharactersOnly(): void
    {
        $name = "🅻🅾🆁🅴🅼·🅸🅿🆂🆄🅼·🅳🅾🅻🅾🆁";

        # Invalid characters stripped and replaced with "not available" instead of empty string
        $expectedFormattedName = "not available";

        $actualFormattedName = $this->paypalApiDataFormatter->formatName($name);
        $this->assertEquals($expectedFormattedName, $actualFormattedName);
    }

    public function testFormatDescriptionLongDescriptionWithMultiByteCharactersAndHtmlTags(): void
    {
        $description =
            "<p>春天來了, 陽光溫暖, 大地復甦. 公園裡,  花兒盛開, 蝴蝶飛舞, 孩子們歡笑奔跑. " .
            "老人們在樹下下棋, 分享往事. 湖水波光粼粼, 鴨子悠閒游動, 微風輕拂, 帶來陣陣花香, " .
            "讓人心曠神怡. 春天來了, 陽光溫暖, 大地復甦.  花兒盛開, 蝴蝶飛舞, 孩子們歡笑奔跑. " .
            "老人們在樹下下棋, 分享往事. 湖水波光粼粼, 鴨子悠閒游動, 微風輕拂, 帶來陣陣花香, 讓人心曠神怡.</p>";

        # Html tags stripped and truncated to the first 127 (multibyte) characters
        $expectedFormattedDescription =
            "春天來了, 陽光溫暖, 大地復甦. 公園裡,  花兒盛開, 蝴蝶飛舞, 孩子們歡笑奔跑. " .
            "老人們在樹下下棋, 分享往事. 湖水波光粼粼, 鴨子悠閒游動, 微風輕拂, 帶來陣陣花香, " .
            "讓人心曠神怡. 春天來了, 陽光溫暖, 大地復甦.  花兒盛開, 蝴蝶飛";

        $actualFormattedDescription = $this->paypalApiDataFormatter->formatDescription($description);
        $this->assertEquals($expectedFormattedDescription, $actualFormattedDescription);
    }

    public function testFormatDescriptionWithInvalidCharactersOnly(): void
    {
        $description = "🅻🅾🆁🅴🅼·🅸🅿🆂🆄🅼·🅳🅾🅻🅾🆁";

        # Invalid characters stripped and replaced with "not available" instead of empty string
        $expectedFormattedDescription = "not available";

        $actualFormattedDescription = $this->paypalApiDataFormatter->formatDescription($description);
        $this->assertEquals($expectedFormattedDescription, $actualFormattedDescription);
    }
}

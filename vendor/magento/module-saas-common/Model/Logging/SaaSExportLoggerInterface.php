<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Model\Logging;

/**
 * Interface used to provide custom log handlers defined in di.xml
 */
interface SaaSExportLoggerInterface extends \Psr\Log\LoggerInterface
{
    /**
     * Pass environment variable "EXPORTER_EXTENDED_LOG" to enable extended logging, for example:
     * EXPORTER_EXTENDED_LOG=1 bin/magento saas:resync --feed=products
     *
     * To enable extended logs permanently, you may add "'EXPORTER_EXTENDED_LOG' => 1" to app/etc/env.php
     *
     * Payload will be stored in var/log/saas-export.log
     *
     * In case error happened, data will be stored in var/log/saas-export-errors.log in format:
     * reason, url, base_uri, response and in case of logs extending will add headers and payload
     */
    public const EXPORTER_EXTENDED_LOG = 'EXPORTER_EXTENDED_LOG';
}

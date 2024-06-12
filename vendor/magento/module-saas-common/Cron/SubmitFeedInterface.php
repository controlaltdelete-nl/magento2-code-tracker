<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SaaSCommon\Cron;

use Magento\SaaSCommon\Model\Exception\UnableSendData;
use Magento\ServicesConnector\Exception\PrivateKeySignException;

/**
 * Class to execute submitting data feed
 */
interface SubmitFeedInterface
{
    /**
     * Submit feed data
     *
     * @param array $data
     * @return bool
     * @throws UnableSendData|PrivateKeySignException
     *
     * TODO: Remove when all feeds are migrated to immediate export
     */
    public function submitFeed(array $data) : bool;

    /**
     *  Execute feed data submission
     *
     * @throws \Zend_Db_Statement_Exception
     */
    public function execute();
}

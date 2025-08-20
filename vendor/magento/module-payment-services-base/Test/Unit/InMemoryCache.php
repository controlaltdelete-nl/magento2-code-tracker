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

namespace Magento\PaymentServicesBase\Test\Unit;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Cache\FrontendInterface;

/**
 * Simple in-memory implementation of CacheInterface for unit tests.
 */
class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, string>
     */
    private array $data = [];

    /**
     * @inheritDoc
     */
    public function load($identifier): string|false
    {
        return $this->data[$identifier] ?? false;
    }

    /**
     * @inheritDoc
     */
    public function save($data, $identifier, $tags = [], $lifeTime = null): bool
    {
        $this->data[$identifier] = $data;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function remove($identifier): bool
    {
        unset($this->data[$identifier]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clean($tags = []): bool
    {
        $this->data = [];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getFrontend(): ?FrontendInterface
    {
        return null;
    }
}

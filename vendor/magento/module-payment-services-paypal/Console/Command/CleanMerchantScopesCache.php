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

namespace Magento\PaymentServicesPaypal\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\PaymentServicesBase\Model\MerchantCacheService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanMerchantScopesCache extends Command
{
    /**
     * @var MerchantCacheService $cacheService
     */
    private MerchantCacheService $cacheService;

    /**
     * @param MerchantCacheService $cacheService
     */
    public function __construct(MerchantCacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('cache:clean:payment_services_merchant_scopes')
            ->setDescription('Clean Payment Services Merchant scopes cache');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheService->cleanScopesFromCache();

        $output->writeln('');
        $output->writeln('<info>Payment Services merchant scopes cleaned from cache</info>');

        return Cli::RETURN_SUCCESS;
    }
}


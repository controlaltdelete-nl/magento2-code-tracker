<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Indexer\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for invalidating indexers.
 */
class IndexerResetStateCommand extends AbstractIndexerManageCommand
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('indexer:reset')
            ->setDescription('Resets indexer status to invalid')
            ->setDefinition($this->getInputList());

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexers = $this->getIndexers($input);
        foreach ($indexers as $indexer) {
            try {
                $indexer->getState()
                    ->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_INVALID)
                    ->save();
                $output->writeln($indexer->getTitle() . ' indexer has been invalidated.');
            } catch (LocalizedException $e) {
                $output->writeln($e->getMessage());
                return Cli::RETURN_FAILURE;
            } catch (\Exception $e) {
                $output->writeln($indexer->getTitle() . ' indexer process unknown error:');
                $output->writeln($e->getMessage());
                return Cli::RETURN_FAILURE;
            }
        }

        return Cli::RETURN_SUCCESS;
    }
}

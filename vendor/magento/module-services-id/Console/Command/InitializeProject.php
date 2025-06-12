<?php
/**
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

namespace Magento\ServicesId\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\ServicesId\Model\ServicesClientInterface;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * CLI command for initializing a new project for a merchant
 */
class InitializeProject extends Command
{
    private const COMMAND_NAME = 'saas:initialize:project';
    private const PROJECT_NAME_OPTION = 'projectName';

    /**
     * @var ServicesConfigInterface
     */
    private ServicesConfigInterface $servicesConfig;

    /**
     * @var ServicesClientInterface
     */
    private ServicesClientInterface $servicesClient;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ServicesConfigInterface $servicesConfig
     * @param ServicesClientInterface $servicesClient
     * @param Json $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        ServicesConfigInterface $servicesConfig,
        ServicesClientInterface $servicesClient,
        Json $serializer,
        LoggerInterface $logger
    ) {
        $this->servicesConfig = $servicesConfig;
        $this->servicesClient = $servicesClient;
        $this->serializer = $serializer;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::PROJECT_NAME_OPTION,
                '-p',
                InputOption::VALUE_REQUIRED,
                'Project Name'
            )
        ];

        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Initialize a new project for the merchant configured in the service connector');
        $this->setDefinition($options);
        parent::configure();
    }

    /**
     * Initialize a new project  for the merchant in registry service
     *
     * {@inheritdoc}
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->servicesConfig->isApiKeySet()) {
            $output->writeln('<error>Commerce Services API Key is not set</error>');
            return Cli::RETURN_FAILURE;
        }

        try {
            $projectName = $input->getOption(self::PROJECT_NAME_OPTION);
            if (empty($projectName)) {
                $output->writeln('<error>Project name is required</error>');
                return Cli::RETURN_FAILURE;
            }

            $url = $this->servicesConfig->getRegistryApiUrl('registry/initialize');
            $payload = ['projectName' => $projectName];
            $data = $this->serializer->serialize($payload);
            $response = $this->servicesClient->request('POST', $url, $data);

            if ($response
                && !empty($response['status'])
                && $response['status'] != 200
            ) {
                $this->logger->error('Project initialization failed.', ['response' => $response]);
                $errorMessage = !empty($response['message']) ? $response['message'] : $response['error'];
                $output->writeln(sprintf('<error>Project initialization failed: %s </error>', $errorMessage));
                $exitCode = Cli::RETURN_FAILURE;
            } else {
                $this->parseResponse($output, $response);
                $exitCode = Cli::RETURN_SUCCESS;
            }
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf('Project initialization failed: %s', $exception->getMessage()),
                ['error' => $exception]
            );
            $output->writeln('<error>Project initialization failed.</error>');
            $exitCode = Cli::RETURN_FAILURE;
        }
        return $exitCode;
    }

    /**
     * Parse the response in a table from the merchant registry service
     *
     * @param OutputInterface $output
     * @param array $response
     * @return void
     */
    private function parseResponse(OutputInterface $output, array $response): void
    {
        $output->writeln('<info>Project data:</info>');
        $table = new Table($output);
        $table->setHeaders([
                'Project Id',
                'Project Name',
                'Environment Id',
                'Environment Name',
                'Environment Type']);
        foreach ($response['results'] as $project) {
            $table->addRow([
                $project['projectId'],
                $project['projectName'],
                $project['environmentId'],
                $project['environmentName'],
                $project['environmentType']
            ]);
        }
        $table->render();
    }
}

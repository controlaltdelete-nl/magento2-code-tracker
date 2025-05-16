<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ServicesId\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\ServicesId\Model\ServicesClientInterface;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * CLI command for listing Saas projects data
 */
class ListProjects extends Command
{
    private const COMMAND_NAME = 'saas:list:projects';
    private const PROJECT_ID_OPTION = 'projectId';
    private const ENVIRONMENT_ID_OPTION = 'environmentId';

    /**
     * @var ServicesConfigInterface
     */
    private ServicesConfigInterface $servicesConfig;

    /**
     * @var ServicesClientInterface
     */
    private ServicesClientInterface $servicesClient;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ServicesConfigInterface $servicesConfig
     * @param ServicesClientInterface $servicesClient
     * @param ScopeConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ServicesConfigInterface $servicesConfig,
        ServicesClientInterface $servicesClient,
        ScopeConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->servicesConfig = $servicesConfig;
        $this->servicesClient = $servicesClient;
        $this->config = $config;
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
                self::PROJECT_ID_OPTION,
                '-p',
                InputOption::VALUE_OPTIONAL,
                'Project identifier'
            ),
            new InputOption(
                self::ENVIRONMENT_ID_OPTION,
                '-e',
                InputOption::VALUE_OPTIONAL,
                'Environment identifier'
            )
        ];

        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Lists projects info for the merchant configured in the service connector');
        $this->setDefinition($options);
        parent::configure();
    }

    /**
     * Request the list of projects from merchant registry service
     *
     * {@inheritdoc}
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = $this->config->getValue($this->servicesConfig::CONFIG_PATH_SERVICES_CONNECTOR_ENVIRONMENT);
        if (empty($environment)) {
            $output->writeln(sprintf(
                '<error>Saas environment [%s] not set</error>',
                $this->servicesConfig::CONFIG_PATH_SERVICES_CONNECTOR_ENVIRONMENT
            ));
            return Cli::RETURN_FAILURE;
        }
        if (!$this->servicesConfig->isApiKeySet()) {
            $output->writeln(sprintf(
                '<error>Saas Api key set not configured for %s environment</error>',
                $environment
            ));
            return Cli::RETURN_FAILURE;
        }

        try {
            $projectId = $input->getOption(self::PROJECT_ID_OPTION);
            $environmentId = $input->getOption(self::ENVIRONMENT_ID_OPTION);
            $response = $this->servicesClient->request('GET', $this->getUrl($projectId, $environmentId));
            if ($response
                && !empty($response['status'])
                && $response['status'] != 200
            ) {
                $this->logger->error('Projects list retrieval failed.', ['response' => $response]);
                $errorMessage = !empty($response['message']) ? $response['message'] : $response['error'];
                $output->writeln(sprintf('<error>Projects list retrieval failed: %s </error>', $errorMessage));
                $exitCode = Cli::RETURN_FAILURE;
            } else {
                $this->parseResponse($output, $response);
                $exitCode = Cli::RETURN_SUCCESS;
            }
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf('Projects list retrieval failed: %s', $exception->getMessage()),
                ['error' => $exception]
            );
            $output->writeln('<error>Projects list retrieval failed.</error>');
            $exitCode = Cli::RETURN_FAILURE;
        }
        return $exitCode;
    }

    /**
     * Build registry API url based on the project and environment identifiers if provided
     *
     * @param string|null $projectId
     * @param string|null $environmentId
     * @return string
     */
    private function getUrl(?string $projectId, ?string $environmentId): string
    {
        if (!empty($environmentId)) {
            $path = sprintf('registry/environments/%s', $environmentId);
        } elseif (!empty($projectId)) {
            $path = sprintf('registry/projects/%s', $projectId);
        } else {
            $path = 'registry';
        }
        return $this->servicesConfig->getRegistryApiUrl($path);
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
        // The response can be a list of projects or a single project (when getByEnvironmentId requested)
        $projectData = $response['results'] ?? [$response];

        if (empty($projectData)) {
            $output->writeln('<comment>No projects found</comment>');
            return;
        }

        $output->writeln('<info>Projects list:</info>');
        $table = new Table($output);
        $table->setHeaders([
                'Project Id',
                'Project Name',
                'Environment Id',
                'Environment Name',
                'Environment Type']);
        foreach ($projectData as $project) {
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

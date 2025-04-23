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

namespace Magento\ServicesId\Plugin;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\ServicesConnector\Exception\KeyNotFoundException;
use Magento\ServicesId\Exception\ServerErrorResponseException;
use Magento\ServicesId\Model\ServicesClientInterface;
use Magento\ServicesId\Model\ServicesConfig;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin to validate registry environment Id  on config import
 * Triggered on app:config:import, config:set
 */
class EnvironmentIdOnConfigImport
{
   /**
    * @var ServicesConfigInterface
    */
    private $servicesConfig;

    /**
     * @var ServicesClientInterface
     */
    private ServicesClientInterface $servicesClient;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ServicesConfigInterface $servicesConfig
     * @param ServicesClientInterface $servicesClient
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     */
    public function __construct(
        ServicesConfigInterface $servicesConfig,
        ServicesClientInterface $servicesClient,
        WriterInterface $configWriter,
        LoggerInterface $logger
    ) {
        $this->servicesConfig = $servicesConfig;
        $this->servicesClient = $servicesClient;
        $this->configWriter = $configWriter;
        $this->logger = $logger;
    }

    /**
     * Interceptor for saving environment id on config import.
     *
     * It validates with merchant registry the environment id before saving it and updates the environment name and
     * type configs accordingly
     *
     * @param Value $subject
     * @param callable $proceed
     * @return Value
     * @throws ServerErrorResponseException
     */
    public function aroundSave(Value $subject, callable $proceed): Value
    {
        if ($subject->getPath() === ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID) {
            $environmentId = $subject->getValue();
            if (empty($environmentId)) {
                return $proceed();
            }

            try {
                $environmentData = $this->validateEnvironmentId($environmentId);
                $environmentName = $environmentData['environmentName'];
                $environmentType = $environmentData['environmentType'];
                $result = $proceed();
                $this->configWriter->save(ServicesConfig::CONFIG_PATH_ENVIRONMENT_NAME, $environmentName);
                $this->configWriter->save(ServicesConfig::CONFIG_PATH_ENVIRONMENT_TYPE, $environmentType);
                $this->logger->info(
                    'Environment data has been set',
                    ['environmentName' => $environmentName, 'environmentType' => $environmentType]
                );
                return $result;
            } catch (ServerErrorResponseException | KeyNotFoundException | \InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                throw $e;
            }
        } else {
            return $proceed();
        }
    }

    /**
     * Checks with merchant registry service the validity of the environment id
     *
     * @param string $environmentId
     * @return array Environment data
     * @throws ServerErrorResponseException|KeyNotFoundException
     */
    private function validateEnvironmentId(string $environmentId): array
    {
        if (!$this->servicesConfig->isApiKeySet()) {
            $errorMessage = 'Commerce Services API Key is not set';
            $this->logger->error($errorMessage);
            throw new KeyNotFoundException($errorMessage);
        }

        $url = $this->servicesConfig->getRegistryApiUrl(sprintf('registry/environments/%s', $environmentId));
        $response = $this->servicesClient->request('GET', $url);
        if ($response
            && !empty($response['status'])
            && $response['status'] != 200
        ) {
            $this->logger->error('Environment data retrieval failed.', ['response' => $response]);
            $errorMessage = !empty($response['message']) ? $response['message'] : $response['error'];
            throw new ServerErrorResponseException(sprintf('Environment data retrieval failed: %s', $errorMessage));
        }

        if ($this->servicesConfig->getProjectId() !== $response['projectId']) {
            $errorMessage = 'Requested environment does not belong to the configured project.';
            $this->logger->error($errorMessage, ['response' => $response]);
            throw new \InvalidArgumentException($errorMessage);
        }
        return $response;
    }
}

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

use Magento\Framework\App\Cache\Type\Config as CacheConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\ServicesConnector\Exception\KeyNotFoundException;
use Magento\ServicesId\Exception\ServerErrorResponseException;
use Magento\ServicesId\Model\ServicesClientInterface;
use Magento\ServicesId\Model\ServicesConfig;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin to validate registry project Id  on config import
 * Triggered on app:config:import, config:set
 */
class ProjectIdOnConfigImport
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
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ServicesConfigInterface $servicesConfig
     * @param ServicesClientInterface $servicesClient
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param LoggerInterface $logger
     */
    public function __construct(
        ServicesConfigInterface $servicesConfig,
        ServicesClientInterface $servicesClient,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        $this->servicesConfig = $servicesConfig;
        $this->servicesClient = $servicesClient;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    /**
     * Interceptor for saving project id on config import.
     *
     * It validates with merchant registry the project id before saving it and updates the project name and
     * environment configs accordingly
     *
     * @param Value $subject
     * @param callable $proceed
     * @return Value
     * @throws ServerErrorResponseException
     */
    public function aroundSave(Value $subject, callable $proceed): Value
    {
        if ($subject->getPath() === ServicesConfig::CONFIG_PATH_PROJECT_ID) {
            $projectId = $subject->getValue();
            if (empty($projectId)) {
                return $proceed();
            }

            try {
                $projectData = $this->validateProjectId($projectId);
                $projectName = $projectData[0]['projectName'];
                $result = $proceed();
                $this->configWriter->save(ServicesConfig::CONFIG_PATH_PROJECT_NAME, $projectName);
                $this->logger->info('Project name has been set', ['projectName' => $projectName]);
                $this->configWriter->delete(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID);
                $this->configWriter->delete(ServicesConfig::CONFIG_PATH_ENVIRONMENT_NAME);
                $this->configWriter->delete(ServicesConfig::CONFIG_PATH_ENVIRONMENT_TYPE);
                $this->cacheTypeList->cleanType(CacheConfig::TYPE_IDENTIFIER);
                $this->logger->info('Environment configs have been deleted');
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
     * Checks with merchant registry service the validity of the project id
     *
     * @param string $projectId
     * @return array Project data
     * @throws ServerErrorResponseException|KeyNotFoundException
     */
    private function validateProjectId(string $projectId): array
    {
        if (!$this->servicesConfig->isApiKeySet()) {
            $errorMessage = 'Commerce Services API Key is not set';
            $this->logger->error($errorMessage);
            throw new KeyNotFoundException($errorMessage);
        }

        $url = $this->servicesConfig->getRegistryApiUrl(sprintf('registry/projects/%s', $projectId));
        $response = $this->servicesClient->request('GET', $url);
        if ($response
            && !empty($response['status'])
            && $response['status'] != 200
        ) {
            $this->logger->error('Projects list retrieval failed.', ['response' => $response]);
            $errorMessage = !empty($response['message']) ? $response['message'] : $response['error'];
            throw new ServerErrorResponseException(sprintf('Projects list retrieval failed: %s', $errorMessage));
        }

        if (empty($response['results'])) {
            $this->logger->error('Project not found', ['response' => $response]);
            throw new \InvalidArgumentException('Project not found');
        }

        return $response['results'];
    }
}

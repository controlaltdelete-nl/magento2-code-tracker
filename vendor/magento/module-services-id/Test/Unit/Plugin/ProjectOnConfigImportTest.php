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

namespace Magento\ServicesId\Test\Unit\Plugin;

use Magento\Framework\App\Cache\Type\Config as CacheConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueInterface;
use Magento\ServicesConnector\Exception\KeyNotFoundException;
use Magento\ServicesId\Exception\ServerErrorResponseException;
use Magento\ServicesId\Model\ServicesClientInterface;
use Magento\ServicesId\Model\ServicesConfig;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Magento\ServicesId\Plugin\ProjectIdOnConfigImport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProjectOnConfigImportTest extends TestCase
{
    /**
     * @var ProjectIdOnConfigImport
     */
    private $projectIdOnConfigImport;

    /**
     * @var ServicesConfigInterface|MockObject
     */
    private $servicesConfig;

    /**
     * @var ServicesClientInterface|MockObject
     */
    private $servicesClient;

    /**
     * @var WriterInterface|MockObject
     */
    private $configWriter;

    /**
     * @var TypeListInterface|MockObject
     */
    private $cacheTypeList;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

   /**
    * @var ValueInterface|MockObject
    */
    private $subject;

    protected function setUp(): void
    {
        $this->servicesConfig = $this->createMock(ServicesConfigInterface::class);
        $this->servicesClient = $this->createMock(ServicesClientInterface::class);
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = $this->getMockBuilder(Value::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPath', 'getValue'])
            ->getMock();

        $this->projectIdOnConfigImport = new ProjectIdOnConfigImport(
            $this->servicesConfig,
            $this->servicesClient,
            $this->configWriter,
            $this->cacheTypeList,
            $this->logger
        );
    }

    public function testAroundSave(): void
    {
        $registryResponse = [
            'status' => 200,
            'results' => [
                0 => [
                    'environmentId' => 'c64abe44-a6fc-4e6e-b167-000000000000',
                    'mageId' => 'MAG005500000',
                    'imsOrganizationId' => '',
                    'organizationId' => 'fd4542e7-d5af-4ad2-a961-0000000000',
                    'organizationName' => 'My Organization',
                    'projectId' => 'project_id',
                    'projectName' => 'project_name',
                    'environmentType' => 'Testing',
                    'environmentName' => 'Testing 4',
                    'accountGroup' => 'Adobe Employee',
                    'entitlementAuthority' => 'MAGENTO',
                    'accountEntitlements' => []
                ],
                1 => [
                    'environmentId' => 'c64abe44-a6fc-4e6e-b167-000000000001',
                    'mageId' => 'MAG005500000',
                    'imsOrganizationId' => '',
                    'organizationId' => 'fd4542e7-d5af-4ad2-a961-0000000000',
                    'organizationName' => 'My Organization',
                    'projectId' => 'project_id',
                    'projectName' => 'project_name',
                    'environmentType' => 'Testing',
                    'environmentName' => 'Testing 1',
                    'accountGroup' => 'Adobe Employee',
                    'entitlementAuthority' => 'MAGENTO',
                    'accountEntitlements' => []
                ],
            ]
        ];
        $this->setUpSubjectExpectations('project_id');
        $this->setUpRegistryService('project_id', $registryResponse);
        $this->expectApiKeySet(true);

        $proceed = function () {
            return $this->setUpExpectedResponse('project_id');
        };

        $this->configWriter->expects($this->once())
            ->method('save')
            ->with(ServicesConfig::CONFIG_PATH_PROJECT_NAME, 'project_name');

        $this->configWriter->expects($this->exactly(3))
            ->method('delete')
            ->willReturnCallback(function (...$args) {
                static $params = [
                    [ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID, 'default', 0],
                    [ServicesConfig::CONFIG_PATH_ENVIRONMENT_NAME, 'default', 0],
                    [ServicesConfig::CONFIG_PATH_ENVIRONMENT_TYPE, 'default', 0]
                ];
                $this->assertSame(array_shift($params), $args);
            });

        $this->cacheTypeList->expects($this->once())
            ->method('cleanType')
            ->with(CacheConfig::TYPE_IDENTIFIER);

        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->projectIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_PROJECT_ID, $result->getPath());
        $this->assertEquals('project_id', $result->getValue());
    }

    public function testAroundSaveWithoutValueSet(): void
    {
        $this->setUpSubjectExpectations(null);
        $proceed = function () {
            return $this->setUpExpectedResponse(null);
        };

        $this->configWriter->expects($this->never())
            ->method('save');
        $this->configWriter->expects($this->never())
            ->method('delete');
        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->projectIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_PROJECT_ID, $result->getPath());
        $this->assertNull($result->getValue());
    }

    public function testAroundSaveNotFoundProjectId(): void
    {
        $this->setupSubjectExpectations('not_found_project_id');
        $this->setUpRegistryService('not_found_project_id', []);
        $this->expectApiKeySet(true);

        $proceed = function () {
            return $this->setUpExpectedResponse('not_found_project_id');
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Project not found', ['response' => []]);

        $this->configWriter->expects($this->never())
            ->method('save');
        $this->configWriter->expects($this->never())
            ->method('delete');

        $this->expectException(\InvalidArgumentException::class);

        $result = $this->projectIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_PROJECT_ID, $result->getPath());
        $this->assertEquals('not_found_project_id', $result->getValue());
    }

    public function testAroundSaveErrorGettingProjectList(): void
    {
        $registryResponse = [
            'status' => 500,
            'error' => 'Internal Server Error'
        ];
        $this->setUpSubjectExpectations('project_id');
        $this->setUpRegistryService('project_id', $registryResponse);
        $this->expectApiKeySet(true);

        $proceed = function () {
            return $this->setUpExpectedResponse('project_id');
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Projects list retrieval failed.', ['response' => $registryResponse]);

        $this->configWriter->expects($this->never())
            ->method('save');
        $this->configWriter->expects($this->never())
            ->method('delete');

        $this->expectException(ServerErrorResponseException::class);

        $result = $this->projectIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_PROJECT_ID, $result->getPath());
        $this->assertEquals('project_id', $result->getValue());
    }

    public function testAroundSaveUnexpectedExceptionGettingProjectList(): void
    {
        $this->setUpSubjectExpectations('project_id');
        $this->expectApiKeySet(true);
        $proceed = function () {
            return $this->setUpExpectedResponse('project_id');
        };

        $registryUrl = 'http://registry_url/registry/projects/project_id';
        $this->servicesConfig->expects($this->once())
            ->method('getRegistryApiUrl')
            ->willReturn($registryUrl);
        $this->servicesClient->expects($this->once())
            ->method('request')
            ->with('GET', $registryUrl)
            ->willthrowException(new \Exception('Unexpected error'));

        $this->configWriter->expects($this->never())
            ->method('save');
        $this->configWriter->expects($this->never())
            ->method('delete');

        $this->expectException(\Exception::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Unexpected error');

        $result = $this->projectIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_PROJECT_ID, $result->getPath());
        $this->assertEquals('project_id', $result->getValue());
    }

    public function testAroundSaveApiKeyNotSet()
    {
        $this->setupSubjectExpectations('project_id');
        $this->expectApiKeySet(false);

        $proceed = function () {
            return $this->setUpExpectedResponse('project_id');
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Commerce Services API Key is not set');

        $this->configWriter->expects($this->never())
            ->method('save');

        $this->expectException(KeyNotFoundException::class);

        $result = $this->projectIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_PROJECT_ID, $result->getPath());
        $this->assertEquals('environment_id', $result->getValue());
    }

    /**
     * Set up expectations for the subject mock.
     *
     * @param string|null $value
     */
    private function setUpSubjectExpectations(?string $value): void
    {
        $this->subject->expects($this->once())
            ->method('getPath')
            ->willReturn(ServicesConfig::CONFIG_PATH_PROJECT_ID);

        $this->subject->expects($this->once())
            ->method('getValue')
            ->willReturn($value);
    }

    /**
     * Set up expectations for the registry service.
     *
     * @param string $projectId
     * @param array $response
     */
    private function setUpRegistryService(string $projectId, array $response): void
    {
        $registryUrl = sprintf('http://registry_url/registry/projects/%s', $projectId);
        $this->servicesConfig->expects($this->once())
            ->method('getRegistryApiUrl')
            ->willReturn($registryUrl);
        $this->servicesClient->expects($this->once())
            ->method('request')
            ->with('GET', $registryUrl)
            ->willReturn($response);
    }

    /**
     * Set up the expected response.
     *
     * @param string|null $projectId
     * @return Value|MockObject
     */
    private function setUpExpectedResponse(?string $projectId): Value
    {
        $value = $this->getMockBuilder(Value::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPath', 'getValue'])
            ->getMock();

        $value->expects($this->once())
            ->method('getPath')
            ->willReturn(ServicesConfig::CONFIG_PATH_PROJECT_ID);

        $value->expects($this->once())
            ->method('getValue')
            ->willReturn($projectId);

        return $value;
    }

    /**
     * Set up expectations for the isApiKeySet method.
     *
     * @param bool $isSet
     * @return void
     */
    private function expectApiKeySet(bool $isSet): void
    {
        $this->servicesConfig->expects($this->once())
            ->method('isApiKeySet')
            ->willReturn($isSet);
    }
}

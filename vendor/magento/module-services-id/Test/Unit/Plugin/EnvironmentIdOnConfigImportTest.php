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

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueInterface;
use Magento\ServicesConnector\Exception\KeyNotFoundException;
use Magento\ServicesId\Exception\ServerErrorResponseException;
use Magento\ServicesId\Model\ServicesClientInterface;
use Magento\ServicesId\Model\ServicesConfig;
use Magento\ServicesId\Model\ServicesConfigInterface;
use Magento\ServicesId\Plugin\EnvironmentIdOnConfigImport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class EnvironmentIdOnConfigImportTest extends TestCase
{
    /**
     * @var EnvironmentIdOnConfigImport
     */
    private $environmentIdOnConfigImport;

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
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = $this->getMockBuilder(Value::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPath', 'getValue'])
            ->getMock();

        $this->environmentIdOnConfigImport = new EnvironmentIdOnConfigImport(
            $this->servicesConfig,
            $this->servicesClient,
            $this->configWriter,
            $this->logger
        );
    }

    public function testAroundSave(): void
    {
        $registryResponse = [
            'environmentId' => '8eb8e3be-728d-4dea-8875-17777777777',
            'mageId' => 'MAG00557654321',
            'imsOrganizationId' => 'B1A17A2E64075749374793@AdobeOrg',
            'organizationId' => 'fd4542e7-d5af-4ad2-a961-1b80000000',
            'organizationName' => 'My Organization',
            'projectId' => 'project_id',
            'projectName' => 'project_name',
            'environmentType' => 'Testing',
            'environmentName' => 'Testing 2',
            'accountGroup' => 'Adobe Employee',
            'entitlementAuthority' => 'MAGENTO',
            'accountEntitlements' => [],
            'featureSet' =>
                [
                    0 => 'Premium Search',
                    1 => 'Commerce Payments Sandbox',
                    2 => 'Product Recommendations',
                    3 => 'Catalog Service',
                    4 => 'Catalog Events',
                ],
            'cloudId' => 'tysqxum7wu3xx',
            'subscriptionEndDate' => null,
            'accountSyncStatus' =>
                [
                    'status' => 'Success',
                    'updatedAt' => '2022-08-11T15:26:31.830Z',
                ],
            'createdAt' => '2022-08-11T15:26:31.830Z',
            'updatedAt' => '2025-02-18T15:09:23.505Z',
            'hipaa' => false,
        ];
        $this->setUpSubjectExpectations('environment_id');
        $this->setUpRegistryService('environment_id', $registryResponse);

        $proceed = function () {
            return $this->setUpExpectedResponse('environment_id');
        };

        $this->expectApiKeySet(true);
        $this->servicesConfig->expects($this->once())
            ->method('getProjectId')
            ->willReturn('project_id');

        $this->configWriter->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (...$args) {
                static $params = [
                    [ServicesConfig::CONFIG_PATH_ENVIRONMENT_NAME, 'Testing 2', 'default', 0],
                    [ServicesConfig::CONFIG_PATH_ENVIRONMENT_TYPE, 'Testing', 'default', 0]
                ];
                $this->assertSame(array_shift($params), $args);
            });

        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->environmentIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID, $result->getPath());
        $this->assertEquals('environment_id', $result->getValue());
    }

    public function testAroundSaveWithoutValueSet(): void
    {
        $this->setUpSubjectExpectations(null);
        $proceed = function () {
            return $this->setUpExpectedResponse(null);
        };

        $this->configWriter->expects($this->never())
            ->method('save');
        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->environmentIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID, $result->getPath());
        $this->assertNull($result->getValue());
    }

    public function testAroundSaveApiKeyNotSet(): void
    {
        $this->setupSubjectExpectations('environment_id');
        $this->expectApiKeySet(false);

        $proceed = function () {
            return $this->setUpExpectedResponse('environment_id');
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Commerce Services API Key is not set');

        $this->configWriter->expects($this->never())
            ->method('save');

        $this->expectException(KeyNotFoundException::class);

        $result = $this->environmentIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID, $result->getPath());
        $this->assertEquals('environment_id', $result->getValue());
    }

    public function testAroundSaveNotFoundEnvironmentId(): void
    {
        $registryResponse = [
            'status' => 404,
            'statusText' => 'Not Found',
            'message' => 'Merchant registry not found',
            'requestId' => 'JZaxdKYRAW93gMSghk3ugrAAWeKmuJtl',
        ];
        $this->setupSubjectExpectations('not_found_environment_id');
        $this->setUpRegistryService('not_found_environment_id', $registryResponse);
        $this->expectApiKeySet(true);

        $proceed = function () {
            return $this->setUpExpectedResponse('not_found_environment_id');
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Environment data retrieval failed.', ['response' => $registryResponse]);

        $this->configWriter->expects($this->never())
            ->method('save');

        $this->expectException(ServerErrorResponseException::class);

        $result = $this->environmentIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID, $result->getPath());
        $this->assertEquals('not_found_environment_id', $result->getValue());
    }

    public function testAroundSaveEnvironmentFromDifferentProject(): void
    {
        $registryResponse = [
            'environmentId' => '8eb8e3be-728d-4dea-8875-17777777777',
            'mageId' => 'MAG00557654321',
            'imsOrganizationId' => 'B1A17A2E64075749374793@AdobeOrg',
            'organizationId' => 'fd4542e7-d5af-4ad2-a961-1b80000000',
            'organizationName' => 'My Organization',
            'projectId' => 'project_id',
            'projectName' => 'project_name',
            'environmentType' => 'Testing',
            'environmentName' => 'Testing 2',
            'accountGroup' => 'Adobe Employee',
            'entitlementAuthority' => 'MAGENTO',
            'accountEntitlements' => [],
            'featureSet' =>
               [
                    0 => 'Premium Search',
                    1 => 'Commerce Payments Sandbox',
                    2 => 'Product Recommendations',
                    3 => 'Catalog Service',
                    4 => 'Catalog Events',
               ],
            'cloudId' => 'tysqxum7wu3xx',
            'subscriptionEndDate' => null,
            'accountSyncStatus' =>
                [
                    'status' => 'Success',
                    'updatedAt' => '2022-08-11T15:26:31.830Z',
                ],
            'createdAt' => '2022-08-11T15:26:31.830Z',
            'updatedAt' => '2025-02-18T15:09:23.505Z',
            'hipaa' => false,
        ];
        $this->setUpSubjectExpectations('environment_id');
        $this->setUpRegistryService('environment_id', $registryResponse);
        $proceed = function () {
            return $this->setUpExpectedResponse('environment_id');
        };
        $this->expectApiKeySet(true);

        $this->servicesConfig->expects($this->once())
            ->method('getProjectId')
            ->willReturn('other_project_id');

        $this->logger->expects($this->any())
            ->method('error')
            ->with('Requested environment does not belong to the configured project.');

        $this->configWriter->expects($this->never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $result = $this->environmentIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID, $result->getPath());
        $this->assertEquals('project_id', $result->getValue());
    }

    public function testAroundSaveUnexpectedExceptionGettingEnvironment(): void
    {
        $this->setUpSubjectExpectations('environment_id');
        $proceed = function () {
            return $this->setUpExpectedResponse('environment_id');
        };
        $this->expectApiKeySet(true);

        $registryUrl = 'http://registry_url/registry/environments/environment_id';
        $this->servicesConfig->expects($this->once())
            ->method('getRegistryApiUrl')
            ->willReturn($registryUrl);
        $this->servicesClient->expects($this->once())
            ->method('request')
            ->with('GET', $registryUrl)
            ->willthrowException(new \Exception('Unexpected error'));

        $this->configWriter->expects($this->never())
            ->method('save');

        $this->expectException(\Exception::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Unexpected error');

        $result = $this->environmentIdOnConfigImport->aroundSave($this->subject, $proceed);
        $this->assertEquals(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID, $result->getPath());
        $this->assertEquals('project_id', $result->getValue());
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
            ->willReturn(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID);

        $this->subject->expects($this->once())
            ->method('getValue')
            ->willReturn($value);
    }

    /**
     * Set up expectations for the registry service.
     *
     * @param string $environmentId
     * @param array $response
     */
    private function setUpRegistryService(string $environmentId, array $response): void
    {
        $registryUrl = sprintf('http://registry_url/registry/environments/%s', $environmentId);
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
     * @param string|null $environmentId
     * @return Value|MockObject
     */
    private function setUpExpectedResponse(?string $environmentId): Value
    {
        $value = $this->getMockBuilder(Value::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPath', 'getValue'])
            ->getMock();

        $value->expects($this->once())
            ->method('getPath')
            ->willReturn(ServicesConfig::CONFIG_PATH_ENVIRONMENT_ID);

        $value->expects($this->once())
            ->method('getValue')
            ->willReturn($environmentId);

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

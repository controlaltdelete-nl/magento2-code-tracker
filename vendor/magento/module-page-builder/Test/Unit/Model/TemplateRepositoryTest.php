<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\PageBuilder\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image\Adapter\AdapterInterface;
use Magento\Framework\Image\Factory;
use Magento\PageBuilder\Api\Data\TemplateSearchResultsInterfaceFactory;
use Magento\PageBuilder\Model\ResourceModel\Template as ResourceTemplate;
use Magento\PageBuilder\Model\ResourceModel\Template\CollectionFactory;
use Magento\PageBuilder\Model\Template;
use Magento\PageBuilder\Model\TemplateFactory;
use Magento\PageBuilder\Model\TemplateRepository;
use Magento\MediaStorage\Helper\File\Storage\Database;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TemplateRepositoryTest extends TestCase
{
    /**
     * @var ResourceTemplate|MockObject
     */
    private $resourceMock;

    /**
     * @var TemplateFactory|MockObject
     */
    private $templateFactoryMock;

    /**
     * @var CollectionFactory|MockObject
     */
    private $templateCollectionFactoryMock;

    /**
     * @var TemplateSearchResultsInterfaceFactory|MockObject
     */
    private $searchResultsFactoryMock;

    /**
     * @var CollectionProcessorInterface|MockObject
     */
    private $collectionProcessorMock;

    /**
     * @var Filesystem|MockObject
     */
    private $filesystemMock;

    /**
     * @var Database|MockObject
     */
    private $mediaStorageMock;

    /**
     * @var Factory|MockObject
     */
    private $imageFactoryMock;

    /**
     * @var TemplateRepository
     */
    private $templateRepository;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(ResourceTemplate::class);
        $this->templateFactoryMock = $this->createMock(TemplateFactory::class);
        $this->templateCollectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->searchResultsFactoryMock = $this->createMock(TemplateSearchResultsInterfaceFactory::class);
        $this->collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->mediaStorageMock = $this->createMock(Database::class);
        $this->imageFactoryMock = $this->createMock(Factory::class);

        $this->templateRepository = new TemplateRepository(
            $this->resourceMock,
            $this->templateFactoryMock,
            $this->templateCollectionFactoryMock,
            $this->searchResultsFactoryMock,
            $this->collectionProcessorMock,
            $this->filesystemMock,
            $this->mediaStorageMock,
            $this->imageFactoryMock
        );
    }

    /**
     * Test for delete method
     *
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    public function testDelete()
    {
        $templateId = 1;
        $templateMock =$this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->addMethods(['getTemplateId'])
            ->onlyMethods(['getPreviewImage', 'getPreviewThumbnailImage'])
            ->getMock();
        ;
        $templateMock->expects($this->once())
            ->method('getTemplateId')
            ->willReturn($templateId);
        $templateMock->expects($this->once())
            ->method('getPreviewImage')
            ->willReturn('preview_image.jpg');
        $templateMock->expects($this->once())
            ->method('getPreviewThumbnailImage')
            ->willReturn('preview_thumb_image.jpg');

        $this->templateFactoryMock->method('create')->willReturn($templateMock);
        $this->resourceMock->expects($this->once())->method('load')->with($templateMock, $templateId);
        $this->resourceMock->expects($this->once())->method('delete')->with($templateMock);

        $mediaDirMock = $this->createMock(WriteInterface::class);
        $this->filesystemMock->method('getDirectoryWrite')->willReturn($mediaDirMock);
        $mediaDirMock->method('isExist')->willReturn(true);
        $mediaDirMock->method('delete')->willReturn(true);
        $this->imageFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(
                function ($path) {
                    $this->assertEquals('preview_image.jpg', $path);
                    return $this->createMock(AdapterInterface::class);
                }
            );
        $this->mediaStorageMock->expects($this->exactly(2))->method('deleteFile');

        $this->assertTrue($this->templateRepository->delete($templateMock));
    }

    /**
     * Test for delete method when throws exception
     *
     * @return void
     * @throws LocalizedException
     */
    public function testDeleteThrowsException()
    {
        $this->expectException(CouldNotDeleteException::class);

        $templateMock =$this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->addMethods(['getTemplateId'])
            ->getMock();
        $templateMock->expects($this->once())
            ->method('getTemplateId')
            ->willReturn(1);

        $this->templateFactoryMock->method('create')->willReturn($templateMock);
        $this->resourceMock->method('load')->willThrowException(new \Exception('Error'));

        $this->templateRepository->delete($templateMock);
    }
}

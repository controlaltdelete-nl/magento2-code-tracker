<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\PageBuilder\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\Factory;
use Magento\PageBuilder\Api\Data\TemplateInterface;
use Magento\PageBuilder\Api\Data\TemplateSearchResultsInterfaceFactory;
use Magento\PageBuilder\Api\TemplateRepositoryInterface;
use Magento\PageBuilder\Model\ResourceModel\Template as ResourceTemplate;
use Magento\PageBuilder\Model\ResourceModel\Template\CollectionFactory as TemplateCollectionFactory;
use Magento\MediaStorage\Helper\File\Storage\Database;

/**
 * Repository for Page Builder Templates
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TemplateRepository implements TemplateRepositoryInterface
{
    /**
     * @var ResourceTemplate
     */
    private $resource;

    /**
     * @var TemplateFactory
     */
    private $templateFactory;

    /**
     * @var TemplateCollectionFactory
     */
    private $templateCollectionFactory;

    /**
     * @var TemplateSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Database
     */
    private $mediaStorage;

    /**
     * @param ResourceTemplate $resource
     * @param TemplateFactory $templateFactory
     * @param TemplateCollectionFactory $templateCollectionFactory
     * @param TemplateSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param Filesystem $filesystem
     * @param Database $mediaStorage
     * @param Factory $imageFactory
     */
    public function __construct(
        ResourceTemplate $resource,
        TemplateFactory $templateFactory,
        TemplateCollectionFactory $templateCollectionFactory,
        TemplateSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        Filesystem $filesystem,
        Database $mediaStorage,
        private readonly Factory $imageFactory
    ) {
        $this->resource = $resource;
        $this->templateFactory = $templateFactory;
        $this->templateCollectionFactory = $templateCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->filesystem = $filesystem;
        $this->mediaStorage = $mediaStorage;
    }

    /**
     * @inheritdoc
     */
    public function save(TemplateInterface $template) : TemplateInterface
    {
        try {
            $this->resource->save($template);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the template: %1', $exception->getMessage())
            );
        }

        return $this->get($template->getId());
    }

    /**
     * @inheritdoc
     */
    public function get($templateId) : TemplateInterface
    {
        $template = $this->templateFactory->create();
        $this->resource->load($template, $templateId);

        if (!$template->getId()) {
            throw new NoSuchEntityException(__('Template with id "%1" does not exist.', $templateId));
        }

        return $template;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->templateCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(TemplateInterface $template) : bool
    {
        try {
            $templateModel = $this->templateFactory->create();
            $this->resource->load($templateModel, $template->getTemplateId());
            $this->resource->delete($templateModel);
            $previewImage = $template->getPreviewImage();
            $previewThumbImage = $templateModel->getPreviewThumbnailImage();
            $this->deletePreviewImage($previewImage);
            $this->deletePreviewImage($previewThumbImage);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the Template: %1', $exception->getMessage())
            );
        }

        return true;
    }

    /**
     * Checks if preview image is valid and tries to delete it
     *
     * @param string $imageName
     * @return void
     * @throws FileSystemException
     */
    private function deletePreviewImage(string $imageName): void
    {
        $isValid = true;
        $mediaDir = $this->filesystem
            ->getDirectoryWrite(DirectoryList::MEDIA);

        try {
            $this->imageFactory->create($mediaDir->getAbsolutePath().$imageName);
        } catch (\Exception) {
            $isValid = false;
        }

        if ($mediaDir->isExist($imageName) && $isValid) {
            $mediaDir->delete($imageName);
        }

        $this->mediaStorage->deleteFile($imageName);
    }

    /**
     * @inheritdoc
     */
    public function deleteById($templateId) : bool
    {
        return $this->delete($this->get($templateId));
    }
}

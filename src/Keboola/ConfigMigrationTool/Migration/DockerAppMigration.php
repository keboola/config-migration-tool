<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Monolog\Logger;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;

abstract class DockerAppMigration implements MigrationInterface
{
    /** @var string */
    protected $originComponentId;

    /** @var string */
    protected $destinationComponentId;

    /** @var Logger */
    protected $logger;

    /** @var  StorageApiService */
    protected $storageApiService;

    /** @var array */
    protected $imageParameters;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->storageApiService = new StorageApiService();
    }

    public function setOriginComponentId(string $id): DockerAppMigration
    {
        $this->originComponentId = $id;
        return $this;
    }

    public function setDestinationComponentId(string $id): DockerAppMigration
    {
        $this->destinationComponentId = $id;
        return $this;
    }

    protected function buildConfigurationObject(string $componentId, array $config): Configuration
    {
        $configuration = new Configuration();
        $configuration->setComponentId($componentId);
        $configuration->setConfigurationId($config['id']);
        $configuration->setName($config['name']);
        $configuration->setDescription($config['description']);
        $configuration->setConfiguration($config['configuration']);
        $configuration->setRowsSortOrder([]);
        return $configuration;
    }

    protected function updateConfigurationOptions(Configuration $configuration, array $options): Configuration
    {
        $c = $configuration->getConfiguration();
        $c = array_replace_recursive($c, $options);
        $configuration->setConfiguration($c);
        return $configuration;
    }

    protected function saveConfigurationOptions(Configuration $configuration, array $options): void
    {
        $c = $this->updateConfigurationOptions($configuration, $options);
        $components = new Components($this->storageApiService->getClient());
        $components->updateConfiguration($c);
    }

    public function setImageParameters(array $imageParameters): void
    {
        $this->imageParameters = $imageParameters;
    }

    public function isConfigurationMigrated(array $configurationObject): bool
    {
        if ((!isset($configurationObject['configuration']['runtime']['migrationStatus'])
                || $configurationObject['configuration']['runtime']['migrationStatus'] != 'success') ||
            // legacy support
            (!isset($configurationObject['configuration']['migrationStatus'])
                || $configurationObject['configuration']['migrationStatus'] != 'success')
        ) {
            return false;
        } else {
            return true;
        }
    }

    public function getConfigurationStatus(array $configurationObject): string
    {
        if (!isset($configurationObject['configuration']['runtime']['migrationStatus'])) {
            return $configurationObject['configuration']['runtime']['migrationStatus'];
        } elseif (!isset($configurationObject['configuration']['migrationStatus'])) {
            // legacy support
            return $configurationObject['configuration']['migrationStatus'];
        } else {
            return 'n/a';
        }
    }
}

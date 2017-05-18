<?php
/**
 * @package config-migration-tool
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Monolog\Logger;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;

abstract class VersionMigration implements MigrationInterface
{
    protected $originComponentId;
    protected $destinationComponentId;
    /** @var Logger  */
    protected $logger;
    /** @var OrchestratorService  */
    protected $orchestratorService;
    /** @var  StorageApiService */
    protected $storageApiService;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->storageApiService = new StorageApiService();
        $this->orchestratorService = new OrchestratorService($this->logger);
    }

    public function setOriginComponentId($id)
    {
        $this->originComponentId = $id;
        return $this;
    }

    public function setDestinationComponentId($id)
    {
        $this->destinationComponentId = $id;
        return $this;
    }

    protected function buildConfigurationObject($componentId, $config)
    {
        $configuration = new Configuration();
        $configuration->setComponentId($componentId);
        $configuration->setConfigurationId($config['id']);
        $configuration->setName($config['name']);
        $configuration->setDescription($config['description']);
        $configuration->setConfiguration($config['configuration']);
        return $configuration;
    }

    protected function updateConfigurationOptions(Configuration $configuration, array $options)
    {
        $c = $configuration->getConfiguration();
        $c = array_merge_recursive($c, $options);
        $configuration->setConfiguration($c);
        return $configuration;
    }

    protected function saveConfigurationOptions(Configuration $configuration, array $options)
    {
        $c = $this->updateConfigurationOptions($configuration, $options);
        $components = new Components($this->storageApiService->getClient());
        $components->updateConfiguration($c);
    }
}

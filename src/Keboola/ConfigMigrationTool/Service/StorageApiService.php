<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Keboola\StorageApi\Options\Components\ListComponentConfigurationsOptions;
use Keboola\StorageApi\TableExporter;

class StorageApiService
{
    /** @var Client */
    private $client;

    /** @var Components */
    private $components;

    public function __construct()
    {
        $this->client = new Client(['token' => getenv('KBC_TOKEN'), 'url' => getenv('KBC_URL')]);
        $this->components = new Components($this->client);
    }

    public function getClient() : Client
    {
        return $this->client;
    }

    public function getConfigurationTables(string $componentId) : array
    {
        $sysBuckets = $this->getConfigurationBuckets($componentId);

        $tables = [];
        foreach ($sysBuckets as $sysBucket) {
            $tables = $this->client->listTables($sysBucket['id']);
        }

        return $tables;
    }

    public function getConfigurationBuckets(string $componentId) : array
    {
        $buckets = $this->client->listBuckets();
        return array_filter($buckets, function ($bucket) use ($componentId) {
            return $bucket['stage'] == Client::STAGE_SYS && strstr($bucket['name'], $componentId);
        });
    }

    public function exportTable(string $tableId) : array
    {
        $tableExporter = new TableExporter($this->client);
        $file = sys_get_temp_dir() . uniqid('config-migration');
        $tableExporter->exportTable($tableId, $file, []);
        return Client::parseCsv(file_get_contents($file));
    }

    public function getConfigurations(string $componentId) : array
    {
        $component = new Components($this->client);
        $options = new ListComponentConfigurationsOptions();
        $options->setComponentId($componentId);
        return $component->listComponentConfigurations($options);
    }

    public function getConfiguration(string $componentId, string $configurationId) : array
    {
        return $this->components->getConfiguration($componentId, $configurationId);
    }

    public function createConfiguration(Configuration $configuration) : array
    {
        return $this->components->addConfiguration($configuration);
    }

    public function addConfigurationRow(Configuration $configuration, string $id, array $rowConfiguration) : array
    {
        $row = new ConfigurationRow($configuration);
        $row->setRowId($id)
            ->setConfiguration($rowConfiguration);
        return $this->components->addConfigurationRow($row);
    }

    public function encryptConfiguration(Configuration $configuration) :Configuration
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://syrup.keboola.com/docker/',
        ]);
        $client->put(sprintf(
            '%s/configs/%s',
            $configuration->getComponentId(),
            $configuration->getConfigurationId()
        ), [
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN'),
            ],
            'form_params' => [
                'configuration' => \GuzzleHttp\json_encode($configuration->getConfiguration()),
            ],
        ]);

        return $configuration;
    }

    public function deleteConfiguration(string $componentId, string $configurationId) : void
    {
        $this->components->deleteConfiguration($componentId, $configurationId);
    }
}

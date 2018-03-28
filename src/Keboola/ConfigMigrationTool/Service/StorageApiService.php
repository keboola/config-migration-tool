<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Service;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
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

    private function getSyrupService() : string
    {
        $services = $this->client->indexAction()['services'];
        foreach ($services as $service) {
            if ($service['id'] == 'syrup') {
                return $service['url'];
            }
        }
        throw new ApplicationException('Syrup service not found');
    }

    private function getProjectId() : string
    {
        $token = $this->client->verifyToken();
        return (string)$token['owner']['id'];
    }

    public function encryptAndSaveConfiguration(Configuration $configuration) : void
    {
        $configurationData = $this->encryptConfiguration($configuration);
        $configuration->setConfiguration($configurationData);
        $this->saveConfiguration($configuration);
    }

    public function saveConfiguration(Configuration $configuration) : void
    {
        $components = new Components($this->client);
        $components->updateConfiguration($configuration);
    }

    public function encryptConfiguration(Configuration $configuration) : array
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->getSyrupService(),
        ]);
        $response = $client->post(sprintf(
            '/docker/encrypt?componentId=%s&projectId=%s',
            $configuration->getComponentId(),
            $this->getProjectId()
        ), [
            'headers' => [
                'Content-type' => 'application/json'
            ],
            'body' => \GuzzleHttp\json_encode($configuration->getConfiguration())
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new UserException("Failed to encrypt configuration: " . $response->getBody());
        }
        return \GuzzleHttp\json_decode($response->getBody(), true);
    }

    public function deleteConfiguration(string $componentId, string $configurationId) : void
    {
        $this->components->deleteConfiguration($componentId, $configurationId);
    }
}

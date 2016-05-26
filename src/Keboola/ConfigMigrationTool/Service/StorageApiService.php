<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 26/05/16
 * Time: 10:33
 */

namespace Keboola\ConfigMigrationTool\Service;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;

class StorageApiService
{
    /** @var Client */
    private $client;

    private $components;

    public function __construct()
    {
        $this->client = new Client(['token' => getenv('KBC_TOKEN')]);
        $this->components = new Components($this->client);
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getConfigurationTables($componentId)
    {
        $buckets = $this->client->listBuckets();
        $sysBuckets = array_filter($buckets, function ($bucket) use ($componentId) {
            return $bucket['stage'] == Client::STAGE_SYS && strstr($bucket['name'], $componentId);
        });

        $tables = [];
        foreach ($sysBuckets as $sysBucket) {
            $tables = $this->client->listTables($sysBucket['id']);
        }

        return $tables;
    }

    public function exportTable($tableId)
    {
        return Client::parseCsv($this->client->exportTable($tableId));
    }

    public function createConfiguration(Configuration $configuration)
    {
        return $this->components->addConfiguration($configuration);
    }

    public function encryptConfiguration(Configuration $configuration)
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
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ],
            'form_params' => [
                'configuration' => \GuzzleHttp\json_encode($configuration->getConfiguration())
            ]
        ]);

        return $configuration;
    }
}

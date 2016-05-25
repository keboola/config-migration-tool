<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 16/05/16
 * Time: 15:13
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\ExDbConfigurator;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class ExDbMigration implements MigrationInterface
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function execute()
    {
        $sapiClient = $this->getSapiClient();
        $tables = $this->getConfigurationTables($sapiClient);

        $createdConfigurations = [];
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                try {
                    $tableData = $sapiClient::parseCsv($sapiClient->exportTable($table['id']));

                    $safe = $this->configurationSaver($sapiClient);
                    /** @var Configuration $configuration */
                    $configuration = $safe($attributes, $tableData);

                    $this->updateOrchestrations($configuration->getComponentId());

                    $createdConfigurations[] = $configuration;
                    $sapiClient->setTableAttribute($table['id'], 'migrationStatus', 'success');
                } catch (\Exception $e) {
                    $sapiClient->setTableAttribute($table['id'], 'migrationStatus', 'error');
                    $this->logger->error("Error occured during migration", ['message' => $e->getMessage()]);
                }
            }
        }

        return $createdConfigurations;
    }

    public function status()
    {
        $tables = $this->getConfigurationTables($this->getSapiClient());
        return array_map(function ($item) {
            $attributes = TableHelper::formatAttributes($item['attributes']);
            return [
                'configId' => $attributes['accountId'],
                'configName' => $attributes['name'],
                'componentId' => sprintf('keboola.ex-db-%s', $attributes['db.driver']),
                'tableId' => $item['id'],
                'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
            ];
        }, $tables);
    }

    private function getConfigurationTables(Client $sapiClient)
    {
        $buckets = $sapiClient->listBuckets();
        $sysBuckets = array_filter($buckets, function ($bucket) {
            return $bucket['stage'] == Client::STAGE_SYS && strstr($bucket['name'], 'ex-db');
        });

        $tables = [];
        foreach ($sysBuckets as $sysBucket) {
            $tables = $sapiClient->listTables($sysBucket['id']);
        }

        return $tables;
    }

    private function getSapiClient()
    {
        return new Client(['token' => getenv('KBC_TOKEN')]);
    }

    private function getComponentsClient($sapiClient)
    {
        return new Components($sapiClient);
    }

    /**
     * @param $sapiClient
     * @return \Closure
     */
    private function configurationSaver($sapiClient)
    {
        $components = $this->getComponentsClient($sapiClient);
        $encrypt = $this->configurationEncryptor();

        return function ($attributes, $tableData) use ($components, $encrypt) {
            $configurator = new ExDbConfigurator();
            $configuration = $configurator->create($attributes);
            $components->addConfiguration($configuration);
            $configuration->setConfiguration($configurator->createConfiguration($attributes, $tableData));

            return $encrypt($configuration);
        };
    }

    /**
     * @return \Closure
     */
    private function configurationEncryptor()
    {
        return function(Configuration $configuration) {
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
        };
    }

    private function updateOrchestrations($componentId)
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://syrup.keboola.com/orchestrator/',
        ]);
        $response = $client->get('orchestrations',[
            'headers' => [
                'X-StorageApi-Token' => getenv('KBC_TOKEN')
            ]
        ]);

        $orchestrations = \GuzzleHttp\json_decode($response->getBody(),true);

        foreach ($orchestrations as $orchestration) {
            $response = $client->get(sprintf('orchestrations/%s/tasks', $orchestration['id']), [
                'headers' => [
                    'X-StorageApi-Token' => getenv('KBC_TOKEN')
                ]
            ]);
            $tasks = \GuzzleHttp\json_decode($response->getBody(),true);
            foreach ($tasks as $task) {
                if (isset($task['componentUrl']) && (false !== strstr($task['componentUrl'], 'ex-db'))) {
                    $task['componentUrl'] = str_replace(
                        'ex-db',
                        $componentId,
                        $tasks['componentUrl']
                    );
                } else if (isset($task['component']) && ('ex-db' == $task['component'])) {
                    $task['component'] = $componentId;
                }
            }
            $client->put(sprintf('orchestrations/%s/tasks', $orchestration['id'], [
                'headers' => [
                    'X-StorageApi-Token' => getenv('KBC_TOKEN')
                ],
                'json' => $tasks
            ]));
        }
    }
}

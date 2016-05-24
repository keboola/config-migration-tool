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
        $components = $this->getComponentsClient($sapiClient);
        $tables = $this->getConfigurationTables($sapiClient);
        $configurator = new ExDbConfigurator();

        $createdConfigurations = [];
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] == 'success') {
                try {
                    $tableData = $sapiClient::parseCsv($sapiClient->exportTable($table['id']));
                    $createdConfigurations[] = $components->addConfiguration(
                        $configurator->configure($attributes, $tableData)
                    );
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
        $sysBuckets = array_filter($buckets, function($bucket) {
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
}

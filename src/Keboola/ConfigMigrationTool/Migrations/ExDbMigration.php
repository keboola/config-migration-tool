<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 16/05/16
 * Time: 15:13
 */

namespace Keboola\ConfigMigrationTool\Migrations;

use Keboola\ConfigMigrationTool\Configurations\ExDbConfiguration;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
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

        $createdConfigurations = [];
        foreach ($tables as $table) {
            try {
                $csvData = $sapiClient->exportTable($table['id']);
                $tableData = $sapiClient::parseCsv($csvData);
                $exDbConfiguration = new ExDbConfiguration();
                $response = $components->addConfiguration($exDbConfiguration->configure($table, $tableData));
                $createdConfigurations[] = $response;
                $sapiClient->setTableAttribute($table['id'], 'migrationStatus', 'success');
            } catch (\Exception $e) {
                $sapiClient->setTableAttribute($table['id'], 'migrationStatus', 'error');
                $this->logger->error("Error occured during migration", ['message' => $e->getMessage()]);
            }
        }

        return $createdConfigurations;
    }

    public function status()
    {
        $tables = $this->getConfigurationTables($this->getSapiClient());
        return array_map(function ($item) {
            $attributes = $this->formatAttributes($item['attributes']);
            return [
                'configId' => $attributes['accountId'],
                'configName' => $attributes['name'],
                'componentId' => sprintf('keboola.ex-db-%s', $attributes['db.driver']),
                'tableId' => $item['id'],
                'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
            ];
        }, $tables);
    }

    private function formatAttributes($attributes)
    {
        $formatted = [];
        foreach ($attributes as $attribute) {
            $formatted[$attribute['name']] = $attribute['value'];
        }
        return $formatted;
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

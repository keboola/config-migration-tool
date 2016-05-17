<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 16/05/16
 * Time: 15:13
 */

namespace Keboola\ConfigMigrationTool\Migrations;

use Keboola\ConfigMigrationTool\Configurations\ExDbConfiguration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;

class ExDbMigration implements MigrationInterface
{
    public function execute($config)
    {
        $sapiClient = new Client(['token' => $config['parameters']['adminToken']]);
        $components = new Components($sapiClient);

        $buckets = $sapiClient->listBuckets();
        $sysBuckets = array_filter($buckets, function($bucket) {
            return $bucket['stage'] == Client::STAGE_SYS && strstr($bucket['name'], 'ex-db');
        });

        $createdConfigurations = [];
        foreach ($sysBuckets as $sysBucket) {
            $tables = $sapiClient->listTables($sysBucket['id']);
            foreach ($tables as $table) {
                $csvData = $sapiClient->exportTable($table['id']);
                $tableData = $sapiClient::parseCsv($csvData);

                $exDbConfiguration = new ExDbConfiguration();
                $response = $components->addConfiguration($exDbConfiguration->configure($table, $tableData));

                $createdConfigurations[] = $response;
            }
        }

        return $createdConfigurations;
    }
}

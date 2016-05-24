<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/05/16
 * Time: 11:10
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Migration\ExDbMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Monolog\Logger;

class ExDbMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $components = new Components($sapiClient);

        $buckets = $sapiClient->listBuckets();
        $sysBuckets = array_filter($buckets, function($bucket) {
            return $bucket['stage'] == Client::STAGE_SYS && strstr($bucket['name'], 'ex-db');
        });
        $oldConfigs = [];
        foreach ($sysBuckets as $sysBucket) {
            $tables = $sapiClient->listTables($sysBucket['id']);
            foreach ($tables as $table) {
                //reset migration status
                try {
                    $sapiClient->deleteTableAttribute($table['id'], 'migrationStatus');
                } catch (\Exception $e) {
                    // do nothing, migrationStatus not set
                }
                $attributes = TableHelper::formatAttributes($table['attributes']);
                $oldConfigs[] = [
                    'name' => $attributes['name'],
                    'driver' => $attributes['db.driver'],
                ];
            }
        }

        $migration = new ExDbMigration(new Logger(APP_NAME));
        $createdConfigurations = $migration->execute();

        $atLeastOneConfigurationHasTables = false;
        foreach ($oldConfigs as $oldCfg) {
            $newConfiguration = $this->findConfigurationByName($createdConfigurations, $oldCfg['name']);
            $this->assertNotFalse($newConfiguration);

            $this->assertEquals($oldCfg['name'], $newConfiguration['name']);
            $this->assertArrayHasKey('parameters', $newConfiguration['configuration']);
            $this->assertArrayHasKey('db', $newConfiguration['configuration']['parameters']);
            $this->assertArrayHasKey('host', $newConfiguration['configuration']['parameters']['db']);
            $this->assertArrayHasKey('port', $newConfiguration['configuration']['parameters']['db']);
            $this->assertArrayHasKey('user', $newConfiguration['configuration']['parameters']['db']);
            $this->assertArrayHasKey('password', $newConfiguration['configuration']['parameters']['db']);

            if (!empty($newConfiguration['configuration']['parameters']['tables'])) {
                $atLeastOneConfigurationHasTables = true;
                $this->assertArrayHasKey('id', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('name', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('query', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('outputTable', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('incremental', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('enabled', $newConfiguration['configuration']['parameters']['tables'][0]);
            }

            //cleanup
            $components->deleteConfiguration('keboola.ex-db-' . $oldCfg['driver'], $newConfiguration['id']);
        }
        $this->assertTrue($atLeastOneConfigurationHasTables);
    }

    public function testStatus()
    {
        $migration = new ExDbMigration(new Logger(APP_NAME));
        $status = $migration->status();

        $this->assertNotEmpty($status);
        $this->assertArrayHasKey('configId', $status[0]);
        $this->assertArrayHasKey('configName', $status[0]);
        $this->assertArrayHasKey('componentId', $status[0]);
        $this->assertArrayHasKey('tableId', $status[0]);
        $this->assertArrayHasKey('status', $status[0]);
    }

    private function findConfigurationByName($configurations, $name)
    {
        foreach ($configurations as $configuration) {
            if ($configuration['name'] == $name) {
                return $configuration;
            }
        }

        return false;
    }
}

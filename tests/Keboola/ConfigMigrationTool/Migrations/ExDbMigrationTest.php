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
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Monolog\Logger;

class ExDbMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $components = new Components($sapiClient);

        $tables = $sapiClient->listTables('sys.c-ex-db');
        foreach ($tables as $table) {
            $sapiClient->dropTable($table['id']);
        }

        $testConfigs = [];
        $testConfigs[] = $this->createOldConfig($sapiClient, 'mysql');
        $testConfigs[] = $this->createOldConfig($sapiClient, 'pgsql');
        $testConfigs[] = $this->createOldConfig($sapiClient, 'oracle');

        $oldConfigs = [];
        foreach ($testConfigs as $tableId) {
            $table = $sapiClient->getTable($tableId);
            $attributes = TableHelper::formatAttributes($table['attributes']);
            //cleanup
            try {
                $components->deleteConfiguration('keboola.ex-db-' . $attributes['db.driver'], $attributes['accountId']);
                $sapiClient->deleteTableAttribute($table['id'], 'migrationStatus');
            } catch (\Exception $e) { }
            $oldConfigs[] = [
                'name' => $attributes['name'],
                'driver' => $attributes['db.driver'],
            ];
        }

        $migration = new ExDbMigration(new Logger(APP_NAME));
        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);

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

            if ($oldCfg['driver'] == 'mysql') {
                $this->assertArrayHasKey('key', $newConfiguration['configuration']['parameters']['db']['ssl']);
                $this->assertEquals('sslkey', $newConfiguration['configuration']['parameters']['db']['ssl']['key']);
                $this->assertArrayHasKey('cert', $newConfiguration['configuration']['parameters']['db']['ssl']);
                $this->assertEquals('sslcert', $newConfiguration['configuration']['parameters']['db']['ssl']['cert']);
                $this->assertArrayHasKey('ca', $newConfiguration['configuration']['parameters']['db']['ssl']);
                $this->assertEquals('sslca', $newConfiguration['configuration']['parameters']['db']['ssl']['ca']);
            }

            if (!empty($newConfiguration['configuration']['parameters']['tables'])) {
                $atLeastOneConfigurationHasTables = true;
                $this->assertArrayHasKey('id', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('name', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('query', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('outputTable', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertArrayHasKey('incremental', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertInternalType('boolean', $newConfiguration['configuration']['parameters']['tables'][0]['incremental']);
                $this->assertArrayHasKey('enabled', $newConfiguration['configuration']['parameters']['tables'][0]);
                $this->assertInternalType('boolean', $newConfiguration['configuration']['parameters']['tables'][0]['enabled']);
            }
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

    private function createOldConfig(Client $sapiClient, $driver)
    {
        $tableId = $sapiClient->createTable(
            'sys.c-ex-db',
            uniqid('test'),
            new CsvFile(ROOT_PATH . 'tests/data/ex-db/test.csv')
        );

        $sapiClient->setTableAttribute($tableId, 'accountId', 'testConfig' . $driver);
        $sapiClient->setTableAttribute($tableId, 'name', 'testConfig' . $driver);
        $sapiClient->setTableAttribute($tableId, 'desc', 'db-ex migration test account - MySql with SSL');
        $sapiClient->setTableAttribute($tableId, 'db.host', '127.0.0.1');
        $sapiClient->setTableAttribute($tableId, 'db.port', '3306');
        $sapiClient->setTableAttribute($tableId, 'db.user', 'root');
        $sapiClient->setTableAttribute($tableId, 'db.password', '123456');
        $sapiClient->setTableAttribute($tableId, 'db.database', 'test');
        $sapiClient->setTableAttribute($tableId, 'db.driver', $driver);

        if ($driver == 'mysql') {
            $sapiClient->setTableAttribute($tableId, 'db.ssl.key', 'sslkey');
            $sapiClient->setTableAttribute($tableId, 'db.ssl.cert', 'sslcert');
            $sapiClient->setTableAttribute($tableId, 'db.ssl.ca', 'sslca');
        }

        return $tableId;
    }
}

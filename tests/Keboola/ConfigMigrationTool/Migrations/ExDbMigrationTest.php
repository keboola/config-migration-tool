<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/05/16
 * Time: 11:10
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Configurations\ExDbConfiguration;
use Keboola\ConfigMigrationTool\Migrations\ExDbMigration;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Symfony\Component\Yaml\Yaml;

class ExDbMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $config = $this->getConfig();

        $sapiClient = new Client(['token' => $config['parameters']['token']]);
        $exDbConfiguration = new ExDbConfiguration();
        $components = new Components($sapiClient);

        $buckets = $sapiClient->listBuckets();
        $sysBuckets = array_filter($buckets, function($bucket) {
            return $bucket['stage'] == Client::STAGE_SYS && strstr($bucket['name'], 'ex-db');
        });
        $oldConfigs = [];
        foreach ($sysBuckets as $sysBucket) {
            $tables = $sapiClient->listTables($sysBucket['id']);
            foreach ($tables as $table) {
                $oldConfigs[] = [
                    'name' => $exDbConfiguration->getTableAttributeValue($table, 'name'),
                    'driver' => $exDbConfiguration->getTableAttributeValue($table, 'db.driver'),
                ];
            }
        }

        $migration = new ExDbMigration();
        $createdConfigurations = $migration->execute($config);

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

    private function findConfigurationByName($configurations, $name)
    {
        foreach ($configurations as $configuration) {
            if ($configuration['name'] == $name) {
                return $configuration;
            }
        }

        return false;
    }

    private function getConfig()
    {
        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../../data/ex-db/config.yml'));
        $config['parameters']['token'] = getenv('EX_DB_TOKEN');
        return $config;
    }
}

<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/05/16
 * Time: 11:10
 */

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\ExDbMigration;
use Keboola\ConfigMigrationTool\Service\ExDbService;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class ExDbMigrationTest extends \PHPUnit_Framework_TestCase
{
    /** @var Client */
    private $sapiClient;

    /** @var Components */
    private $components;

    public function setUp()
    {
        $this->sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $this->components = new Components($this->sapiClient);
    }

    public function testExecute()
    {
        if (!$this->sapiClient->bucketExists('sys.c-ex-db')) {
            $this->sapiClient->createBucket('ex-db', 'sys');
        }
        $tables = $this->sapiClient->listTables('sys.c-ex-db');
        foreach ($tables as $table) {
            $this->sapiClient->dropTable($table['id']);
        }

        $componentCfgs = $this->components->getComponentConfigurations('ex-db');
        foreach ($componentCfgs as $cfg) {
            $this->components->deleteConfiguration('ex-db', $cfg['id']);
        }

        $testConfigs = [];
        $testConfigs[] = $this->createOldConfig('mysql');
        $testConfigs[] = $this->createOldConfig('pgsql');
        $testConfigs[] = $this->createOldConfig('oracle');

        $migration = new ExDbMigration(new Logger(APP_NAME));
        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertCount(3, $createdConfigurations);

        $atLeastOneConfigurationHasTables = false;
        foreach ($testConfigs as $oldCfg) {
            $newConfiguration = $this->findConfigurationByName($createdConfigurations, $oldCfg['name']);
            $this->assertNotFalse($newConfiguration);
            $this->assertEquals($oldCfg['name'], $newConfiguration->getName());

            $this->assertArrayHasKey('parameters', $newConfiguration->getConfiguration());
            $parameters = $newConfiguration->getConfiguration()['parameters'];
            $this->assertArrayHasKey('db', $parameters);
            $this->assertArrayHasKey('host', $parameters['db']);
            $this->assertArrayHasKey('port', $parameters['db']);
            $this->assertArrayHasKey('user', $parameters['db']);
            $this->assertArrayHasKey('#password', $parameters['db']);

            if ($oldCfg['driver'] == 'mysql') {
                $this->assertArrayHasKey('key', $parameters['db']['ssl']);
                $this->assertEquals('sslkey', $parameters['db']['ssl']['key']);
                $this->assertArrayHasKey('cert', $parameters['db']['ssl']);
                $this->assertEquals('sslcert', $parameters['db']['ssl']['cert']);
                $this->assertArrayHasKey('ca', $parameters['db']['ssl']);
                $this->assertEquals('sslca', $parameters['db']['ssl']['ca']);
            }

            if (!empty($parameters['tables'])) {
                $atLeastOneConfigurationHasTables = true;
                $this->assertArrayHasKey('id', $parameters['tables'][0]);
                $this->assertArrayHasKey('name', $parameters['tables'][0]);
                $this->assertArrayHasKey('query', $parameters['tables'][0]);
                $this->assertArrayHasKey('outputTable', $parameters['tables'][0]);
                $this->assertArrayHasKey('incremental', $parameters['tables'][0]);
                $this->assertInternalType('boolean', $parameters['tables'][0]['incremental']);
                $this->assertArrayHasKey('primaryKey', $parameters['tables'][0]);
                $this->assertInternalType('array', $parameters['tables'][0]['primaryKey']);
                $this->assertArrayHasKey('enabled', $parameters['tables'][0]);
                $this->assertInternalType('boolean', $parameters['tables'][0]['enabled']);
            }
        }
        $this->assertTrue($atLeastOneConfigurationHasTables);
    }

    public function testOrchestrationUpdate()
    {
        $oldComponentId = 'ex-db';
        $newComponentId = 'keboola.ex-db-mysql';
        $orchestratorService = new OrchestratorService(new Logger(APP_NAME));
        // create orchestration
        $orchestration = $orchestratorService->request('post', 'orchestrations', [
            'json' => [
                "name" => "Ex DB Migration Test Orchestrator",
                "tasks" => [
                    [
                        "component" => "ex-db",
                        "action" => "run",
                        "actionParameters" => [
                            "config" => "testing"
                        ],
                        "continueOnFailure" => false,
                        "timeoutMinutes" => null,
                        "active" => true
                    ],
                    [
                        "component" => "ex-db",
                        "action" => "run",
                        "actionParameters" => [
                            "account" => "testing2"
                        ],
                        "continueOnFailure" => false,
                        "timeoutMinutes" => null,
                        "active" => true
                    ]
                ]
            ]
        ]);

        // test affected orchestrations
        $affectedOrchestrations = $orchestratorService->getOrchestrations($oldComponentId, 'keboola.ex-db-');
        $this->assertNotEmpty($affectedOrchestrations);
        $orchestrationIsBetweenAffected = false;
        foreach ($affectedOrchestrations as $affected) {
            if ($affected['id'] == $orchestration['id']) {
                $this->assertTrue($affected['hasOld']);
                $orchestrationIsBetweenAffected = true;
            }
        }
        $this->assertTrue($orchestrationIsBetweenAffected);

        // test update orchestration
        $updatedOrchestrations = [];
        foreach (['testing', 'testing2'] as $configId) {
            $configuration = new Configuration();
            $configuration->setComponentId($newComponentId);
            $configuration->setConfigurationId($configId);
            $configuration->setName($configId);

            $updatedOrchestrations = array_merge(
                $orchestratorService->updateOrchestrations($oldComponentId, $configuration),
                $updatedOrchestrations
            );
        }

        $this->assertNotEmpty($updatedOrchestrations);
        $orchestrationIsUpdated = false;
        foreach ($updatedOrchestrations as $updated) {
            // is updated?
            if ($updated['id'] == $orchestration['id']) {
                $orchestrationIsUpdated = true;
            }
            // get tasks
            $tasks = $orchestratorService->getTasks($updated['id']);
            $this->assertNotEmpty($tasks);
            foreach ($tasks as $task) {
                if (isset($task['component'])) {
                    $this->assertEquals($newComponentId, $task['component']);
                }
                $this->assertNotEmpty($task['actionParameters']['config']);
            }
        }
        $this->assertTrue($orchestrationIsUpdated);

        // check affected orchestration after migration
        $affectedOrchestrations = $orchestratorService->getOrchestrations($oldComponentId, 'keboola.ex-db-');
        $this->assertNotEmpty($affectedOrchestrations);
        foreach ($affectedOrchestrations as $affected) {
            if ($affected['id'] == $orchestration['id']) {
                $this->assertTrue($affected['hasNew']);
            }
        }

        // cleanup
        $orchestratorService->request('delete', sprintf('orchestrations/%s', $orchestration['id']));
    }

    public function testStatus()
    {
        $migration = new ExDbMigration(new Logger(APP_NAME));
        $status = $migration->status();

        $this->assertNotEmpty($status);
        $this->assertArrayHasKey('configId', $status['configurations'][0]);
        $this->assertArrayHasKey('configName', $status['configurations'][0]);
        $this->assertArrayHasKey('componentId', $status['configurations'][0]);
        $this->assertArrayHasKey('tableId', $status['configurations'][0]);
        $this->assertArrayHasKey('status', $status['configurations'][0]);
        $this->assertArrayHasKey('orchestrations', $status);
    }

    private function findConfigurationByName($configurations, $name)
    {
        /** @var Configuration $configuration */
        foreach ($configurations as $configuration) {
            if ($configuration->getName() == $name) {
                return $configuration;
            }
        }

        return false;
    }

    private function createOldConfig($driver)
    {
        $exDbService = new ExDbService(new Logger(APP_NAME));
        $cfgId = uniqid('test');
        $config = $exDbService->request('post', 'configs', [
            'json' =>  [
                'id' => $cfgId,
                'name' => $cfgId,
                'description' => 'db-ex migration test account ' . $driver
            ]
        ]);
        $config['driver'] = $driver;

        $exDbService->request('post', sprintf('configs/%s/queries', $config['id']), [
            'json' => [
                'name' => 'testQuery',
                'query' => 'SELECT * FROM test',
                'outputTable' => 'in.c-main.test',
                'incremental' => 0,
                'primaryKey' => 'id',
                'enabled' => 1
            ]
        ]);

        $body = [
            'host' => 'localhost',
            'driver' => $driver,
            'database' => 'test',
            'user' => 'test',
            'password' => 'migrationTest123',
            'port' => 1234
        ];

        if ($driver == 'mysql') {
            $body['ssl'] = [
                'ca' => 'sslca',
                'key' => 'sslkey',
                'cert' => 'sslcert'
            ];
        }

        $exDbService->request('post', sprintf('configs/%s/credentials', $config['id']), [
            'json' => $body
        ]);

        $configuration = new Configuration();
        $configuration->setComponentId('ex-db');
        $configuration->setConfigurationId($cfgId);
        $configuration->setName($cfgId);
        $configuration->setDescription("blabla");
        $this->components->addConfiguration($configuration);

        return $config;
    }
}

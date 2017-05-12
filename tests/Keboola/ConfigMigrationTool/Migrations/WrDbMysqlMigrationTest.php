<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/01/17
 * Time: 14:15
 */

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\WrDbMigration;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\ConfigMigrationTool\Test\WrDbTest;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class WrDbMysqlMigrationTest extends WrDbTest
{
    public function testExecute()
    {
        $expectedConfig = json_decode(file_get_contents(
            ROOT_PATH . '/tests/data/' . $this->getOldComponentId() . '/expected-config.json'
        ), true);
        $this->createOldConfig();
        $sapiService = new StorageApiService();
        $migration = new WrDbMigration($this->getLogger(), $this->driver);
        $createdConfigurations = $migration->execute();

        /** @var Configuration $configuration */
        foreach ($createdConfigurations as $configuration) {
            if ($configuration->getConfigurationId() == 'migration') {
                $this->assertEquals($this->getNewComponentId(), $configuration->getComponentId());
                $config = $configuration->getConfiguration();
                $this->assertArrayHasKey('parameters', $config);
                $parameters = $config['parameters'];
                $this->assertArrayHasKey('db', $parameters);
                $this->assertArrayHasKey('tables', $parameters);

                foreach ($expectedConfig['parameters']['db'] as $k => $v) {
                    $this->assertArrayHasKey($k, $parameters['db']);
                }

                $table = array_shift($parameters['tables']);
                $this->assertArrayHasKey('dbName', $table);
                $this->assertArrayHasKey('export', $table);
                $this->assertArrayHasKey('tableId', $table);
                $this->assertArrayHasKey('items', $table);

                $column = array_shift($table['items']);
                $this->assertArrayHasKey('name', $column);
                $this->assertArrayHasKey('dbName', $column);
                $this->assertArrayHasKey('type', $column);
                $this->assertArrayHasKey('size', $column);
                $this->assertArrayHasKey('nullable', $column);
                $this->assertArrayHasKey('default', $column);
            }

            // clear created configurations
            $sapiService->deleteConfiguration($this->getNewComponentId(), $configuration->getConfigurationId());
        }
    }

    public function testOrchestrationUpdate()
    {
        $orchestratorService = new OrchestratorService($this->getLogger());
        // create orchestration
        $orchestration = $orchestratorService->request('post', 'orchestrations', [
            'json' => [
                "name" => "Wr DB Mysql Migration Test Orchestrator",
                "tasks" => [
                    [
                        "component" => $this->getOldComponentId(),
                        "action" => "run",
                        "actionParameters" => [
                            "config" => "migration"
                        ],
                        "continueOnFailure" => false,
                        "timeoutMinutes" => null,
                        "active" => true
                    ]
                ]
            ]
        ]);

        // test affected orchestrations
        $affectedOrchestrations = $orchestratorService->getOrchestrations(
            $this->getOldComponentId(),
            $this->getNewComponentId()
        );
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

        $configuration = new Configuration();
        $configuration->setComponentId($this->getNewComponentId());
        $configuration->setConfigurationId('migration');
        $configuration->setName('migration');

        $updatedOrchestrations = array_merge(
            $orchestratorService->updateOrchestrations($this->getOldComponentId(), $configuration),
            $updatedOrchestrations
        );

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
                    $this->assertEquals($this->getNewComponentId(), $task['component']);
                }
                $this->assertNotEmpty($task['actionParameters']['config']);
            }
        }
        $this->assertTrue($orchestrationIsUpdated);

        // check affected orchestration after migration
        $affectedOrchestrations = $orchestratorService->getOrchestrations(
            $this->getOldComponentId(),
            $this->getNewComponentId()
        );
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
        $migration = new WrDbMigration($this->getLogger(), $this->driver);
        $status = $migration->status();

        foreach ($status['configurations'] as $configuration) {
            $this->assertNotEmpty($status);
            $this->assertArrayHasKey('configId', $configuration);
            $this->assertArrayHasKey('configName', $configuration);
            $this->assertArrayHasKey('componentId', $configuration);
            $this->assertArrayHasKey('tableId', $configuration);
            $this->assertArrayHasKey('status', $configuration);
        }

        $this->assertArrayHasKey('orchestrations', $status);
    }

    private function getLogger()
    {
        return new Logger(APP_NAME, [
            new \Keboola\ConfigMigrationTool\Logger\InfoHandler(),
            new \Monolog\Handler\StreamHandler('php://stderr', Logger::NOTICE)
        ]);
    }
}

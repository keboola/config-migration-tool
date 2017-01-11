<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/01/17
 * Time: 14:15
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Migration\WrDbMigration;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class WrDbOracleMigrationTest extends WrDbTest
{
    public function testExecute()
    {
        $expectedConfig = json_decode(file_get_contents(
            ROOT_PATH . '/tests/data/wr-db-mysql/expected-config.json'
        ), true);
        $this->createOldConfig();
        $sapiService = new StorageApiService();
        $migration = new WrDbMigration($this->getLogger());
        $createdConfigurations = $migration->execute();

        /** @var Configuration $configuration */
        foreach ($createdConfigurations as $configuration) {
            if ($configuration->getConfigurationId() == 'migration') {
                $this->assertEquals('keboola.wr-db-mysql', $configuration->getComponentId());
                $config = $configuration->getConfiguration();
                $this->assertArrayHasKey('parameters', $config);
                $parameters = $config['parameters'];
                $this->assertArrayHasKey('db', $parameters);
                $this->assertArrayHasKey('tables', $parameters);
                $this->assertEquals($expectedConfig, $config);
            }

            // clear created configurations
            $sapiService->deleteConfiguration('keboola.wr-db-mysql', $configuration->getConfigurationId());
        }
    }

    public function testOrchestrationUpdate()
    {
        $oldComponentId = 'wr-db-mysql';
        $newComponentId = 'keboola.wr-db-mysql';
        $orchestratorService = new OrchestratorService($this->getLogger());
        // create orchestration
        $orchestration = $orchestratorService->request('post', 'orchestrations', [
            'json' => [
                "name" => "Wr DB Mysql Migration Test Orchestrator",
                "tasks" => [
                    [
                        "component" => $oldComponentId,
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
        $affectedOrchestrations = $orchestratorService->getOrchestrations($oldComponentId, $newComponentId);
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
        $configuration->setComponentId($newComponentId);
        $configuration->setConfigurationId('migration');
        $configuration->setName('migration');

        $updatedOrchestrations = array_merge(
            $orchestratorService->updateOrchestrations($oldComponentId, $configuration),
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
                    $this->assertEquals($newComponentId, $task['component']);
                }
                $this->assertNotEmpty($task['actionParameters']['config']);
            }
        }
        $this->assertTrue($orchestrationIsUpdated);

        // check affected orchestration after migration
        $affectedOrchestrations = $orchestratorService->getOrchestrations($oldComponentId, $newComponentId);
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
        $migration = new WrDbMigration($this->getLogger());
        $status = $migration->status();

        $this->assertNotEmpty($status);
        $this->assertArrayHasKey('configId', $status['configurations'][0]);
        $this->assertArrayHasKey('configName', $status['configurations'][0]);
        $this->assertArrayHasKey('componentId', $status['configurations'][0]);
        $this->assertArrayHasKey('tableId', $status['configurations'][0]);
        $this->assertArrayHasKey('status', $status['configurations'][0]);
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

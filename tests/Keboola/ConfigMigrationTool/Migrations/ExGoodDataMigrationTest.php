<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Migration\ExGoodDataMigration;
use Keboola\ConfigMigrationTool\Service\ExGoodDataService;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class ExGoodDataMigrationTest extends ExGoodDataTest
{
    /** @var Client */
    private $sapiClient;

    /** @var Components */
    private $components;

    public function setUp()
    {
        parent::setUp();
        $this->sapiClient = new Client(['token' => getenv('KBC_TOKEN')]);
        $this->components = new Components($this->sapiClient);
    }

    public function testExecute()
    {
        $pid = 'wasc4gjy5sphvlt0wjx5fqys5q6bh38j';
        $writerId = uniqid();
        $config1 = $this->createOldConfig();
        $config2 = $this->createOldConfig();
        $testConfigs = [
            [
                'id' => $config1,
                'name' => $config1,
                'description' => 'ex-gooddata migration test'
            ],
            [
                'id' => $config2,
                'name' => $config2,
                'description' => 'ex-gooddata migration test'
            ]
        ];


        $migration = new ExGoodDataMigration(new Logger(APP_NAME));
        $migration->setService(new ExGoodDataTestService($testConfigs, [$pid => $writerId]));

        $createdConfigurations = $migration->execute();
        $this->assertNotEmpty($createdConfigurations);
        $this->assertCount(2, $createdConfigurations);

        foreach ($testConfigs as $oldCfg) {
            $newConfiguration = $this->findConfigurationByName($createdConfigurations, $oldCfg['name']);
            $this->assertNotFalse($newConfiguration);
            $this->assertEquals($oldCfg['name'], $newConfiguration->getName());

            $this->assertArrayHasKey('parameters', $newConfiguration->getConfiguration());
            $parameters = $newConfiguration->getConfiguration()['parameters'];
            $this->assertArrayHasKey('writer_id', $parameters);
            $this->assertEquals($writerId, $parameters['writer_id']);
            $this->assertArrayHasKey('reports', $parameters);
            $this->assertCount(1, $parameters['reports']);
        }
    }

    public function testOrchestrationUpdate()
    {
        $oldComponentId = 'ex-gooddata';
        $newComponentId = 'keboola.ex-gooddata';
        $orchestratorService = new OrchestratorService(new Logger(APP_NAME));
        // create orchestration
        $orchestration = $orchestratorService->request('post', 'orchestrations', [
            'json' => [
                "name" => "Ex GD Migration Test Orchestrator",
                "tasks" => [
                    [
                        "component" => "ex-gooddata",
                        "action" => "run",
                        "actionParameters" => [
                            "config" => "testing"
                        ],
                        "continueOnFailure" => false,
                        "timeoutMinutes" => null,
                        "active" => true
                    ],
                    [
                        "component" => "ex-gooddata",
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
        $config1 = $this->createOldConfig();
        $migration = new ExGoodDataMigration(new Logger(APP_NAME));
        $status = $migration->status();

        $this->assertNotEmpty($status);
        $this->assertArrayHasKey('configId', $status['configurations'][0]);
        $this->assertArrayHasKey('configName', $status['configurations'][0]);
        $this->assertArrayHasKey('componentId', $status['configurations'][0]);
        $this->assertArrayHasKey('tableId', $status['configurations'][0]);
        $this->assertArrayHasKey('status', $status['configurations'][0]);
        $this->assertArrayHasKey('orchestrations', $status);
        $this->assertEquals($config1, $status['configurations'][0]['configId']);
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
}

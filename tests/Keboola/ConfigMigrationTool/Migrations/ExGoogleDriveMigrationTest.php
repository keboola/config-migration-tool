<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 10:36
 */

namespace Keboola\ConfigMigrationTool\Test;

use Keboola\ConfigMigrationTool\Migration\ExGoogleAnalyticsMigration;
use Keboola\ConfigMigrationTool\Migration\ExGoogleDriveMigration;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class ExGoogleDriveMigrationTest extends ExGoogleDriveTest
{
    public function testExecute()
    {
        $testConfigIds = $this->createOldConfigs();
        $sapiService = new StorageApiService();
        $migration = new ExGoogleDriveMigration($this->getLogger());
        $createdConfigurations = $migration->execute();

        /** @var Configuration $configuration */
        foreach ($createdConfigurations as $configuration) {
            $this->assertContains($configuration->getConfigurationId(), $testConfigIds);
            $this->assertEquals('keboola.ex-google-drive', $configuration->getComponentId());
            $config = $configuration->getConfiguration();
            $this->assertArrayHasKey('authorization', $config);
            $this->assertArrayHasKey('parameters', $config);
            $parameters = $config['parameters'];
            $this->assertArrayHasKey('sheets', $parameters);
            $sheets = $parameters['sheets'];

            foreach ($sheets as $sheet) {
                $this->assertArrayHasKey('id', $sheet);
                $this->assertArrayHasKey('fileId', $sheet);
                $this->assertArrayHasKey('fileTitle', $sheet);
                $this->assertArrayHasKey('sheetId', $sheet);
                $this->assertArrayHasKey('sheetTitle', $sheet);
                $this->assertArrayHasKey('outputTable', $sheet);
                $this->assertArrayHasKey('enabled', $sheet);
                $this->assertArrayHasKey('header', $sheet);
            }

            // clear created configurations
            $sapiService->deleteConfiguration('keboola.ex-google-drive', $configuration->getConfigurationId());
            $key = array_search($configuration->getConfigurationId(), $testConfigIds);
            unset($testConfigIds[$key]);
        }

        // all configs migrated
        $this->assertEmpty($testConfigIds);
    }

    public function testOrchestrationUpdate()
    {
        $oldComponentId = 'ex-google-drive';
        $newComponentId = 'keboola.ex-google-drive';
        $orchestratorService = new OrchestratorService($this->getLogger());
        // create orchestration
        $orchestration = $orchestratorService->request('post', 'orchestrations', [
            'json' => [
                "name" => "Ex Google Drive Migration Test Orchestrator",
                "tasks" => [
                    [
                        "component" => $oldComponentId,
                        "action" => "run",
                        "actionParameters" => [
                            "config" => "testing"
                        ],
                        "continueOnFailure" => false,
                        "timeoutMinutes" => null,
                        "active" => true
                    ],
                    [
                        "component" => $oldComponentId,
                        "action" => "run",
                        "actionParameters" => [
                            "account" => "testing2",
                            "since" => "-30 days",
                            "until" => "-2 days"
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
        $migration = new ExGoogleAnalyticsMigration($this->getLogger());
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

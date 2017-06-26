<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 23/05/16
 * Time: 10:36
 */

namespace Keboola\ConfigMigrationTool\Test\Migrations;

use Keboola\ConfigMigrationTool\Migration\WrGoogleDriveMigration;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\ConfigMigrationTool\Test\WrGoogleDriveTest;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class WrGoogleDriveMigrationTest extends WrGoogleDriveTest
{
    /** @var OrchestratorService */
    private $orchestratorService;

    public function setUp()
    {
        parent::setUp();
        $this->orchestratorService = new OrchestratorService($this->getLogger());
    }

    public function testExecute()
    {
        $expectedConfigDrive = json_decode(file_get_contents(
            ROOT_PATH . '/tests/data/wr-google-drive/expected-config-drive.json'
        ), true);
        $expectedConfigSheets = json_decode(file_get_contents(
            ROOT_PATH . '/tests/data/wr-google-drive/expected-config-sheets.json'
        ), true);

        $testConfigIds = $this->createOldConfigs();

        $sapiService = new StorageApiService();
        $migration = new WrGoogleDriveMigration($this->getLogger());
        $createdConfigurations = $migration->execute();

        /** @var Configuration $configuration */
        foreach ($createdConfigurations as $configuration) {
            $this->assertContains($configuration->getConfigurationId(), $testConfigIds, "", true);
            $this->assertContains(
                $configuration->getComponentId(),
                ['keboola.wr-google-drive', 'keboola.wr-google-sheets']
            );
            $config = $configuration->getConfiguration();
            $this->assertArrayHasKey('authorization', $config);
            $this->assertArrayHasKey('parameters', $config);
            $parameters = $config['parameters'];
            $this->assertArrayHasKey('tables', $parameters);
            $tables = $parameters['tables'];

            foreach ($tables as $sheet) {
                $this->assertArrayHasKey('id', $sheet);
                $this->assertArrayHasKey('fileId', $sheet);
                $this->assertArrayHasKey('title', $sheet);
                $this->assertArrayHasKey('tableId', $sheet);
                $this->assertArrayHasKey('enabled', $sheet);
                $this->assertArrayHasKey('action', $sheet);
                $this->assertArrayHasKey('folder', $sheet);

                if ($configuration->getComponentId() == 'keboola.wr-google-sheets') {
                    $this->assertArrayHasKey('sheetId', $sheet);
                    $this->assertArrayHasKey('sheetTitle', $sheet);
                }
            }

            unset($config['authorization']);
            sort($config['parameters']['tables']);
            sort($config['storage']['input']['tables']);

            if ($configuration->getComponentId() == 'keboola.wr-google-sheets') {
                $this->assertEquals($expectedConfigSheets, $config);
            } else {
                $this->assertEquals($expectedConfigDrive, $config);
            }

            // clear created configurations
            $sapiService->deleteConfiguration('keboola.wr-google-drive', $configuration->getConfigurationId());
            $sapiService->deleteConfiguration('keboola.wr-google-sheets', $configuration->getConfigurationId());
        }
    }

    public function testOrchestrationUpdate()
    {
        $oldComponentId = 'wr-google-drive';
        $driveComponentId = 'keboola.wr-google-drive';
        $sheetsComponentId = 'keboola.wr-google-sheets';

        // create orchestration
        $orchestration = $this->orchestratorService->request('post', 'orchestrations', [
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
                            "account" => "testing2"
                        ],
                        "continueOnFailure" => false,
                        "timeoutMinutes" => null,
                        "active" => true
                    ]
                ]
            ]
        ]);

        // test affected orchestrations - drive
        $driveOrchestrations = $this->orchestratorService->getOrchestrations($oldComponentId, $driveComponentId);
        $this->assertNotEmpty($driveOrchestrations);
        $orchestrationIsBetweenAffected = false;
        foreach ($driveOrchestrations as $affected) {
            if ($affected['id'] == $orchestration['id']) {
                $this->assertTrue($affected['hasOld']);
                $orchestrationIsBetweenAffected = true;
            }
        }
        $this->assertTrue($orchestrationIsBetweenAffected);

        // test affected orchestrations - sheets
        $sheetsOrchestrations = $this->orchestratorService->getOrchestrations($oldComponentId, $sheetsComponentId);
        $this->assertNotEmpty($sheetsOrchestrations);
        $orchestrationIsBetweenAffected = false;
        foreach ($sheetsOrchestrations as $affected) {
            if ($affected['id'] == $orchestration['id']) {
                $this->assertTrue($affected['hasOld']);
                $orchestrationIsBetweenAffected = true;
            }
        }
        $this->assertTrue($orchestrationIsBetweenAffected);

        // test update orchestration - drive
        $updatedOrchestrations = $this->updateOrchestration($oldComponentId, $driveComponentId);
        $this->assertOrchestration($updatedOrchestrations, $orchestration, $oldComponentId, $driveComponentId);

        // test update orchestration - drive
        $updatedOrchestrations = $this->updateOrchestration($oldComponentId, $driveComponentId);
        $this->assertOrchestration($updatedOrchestrations, $orchestration, $oldComponentId, $driveComponentId);

        // cleanup
        $this->orchestratorService->request('delete', sprintf('orchestrations/%s', $orchestration['id']));
    }

    private function updateOrchestration($oldComponentId, $newComponentId)
    {
        $updatedOrchestrations = [];
        foreach (['testing', 'testing2'] as $configId) {
            $configuration = new Configuration();
            $configuration->setComponentId($newComponentId);
            $configuration->setConfigurationId($configId);
            $configuration->setName($configId);

            $updatedOrchestrations = array_merge(
                $this->orchestratorService->updateOrchestrations($oldComponentId, $configuration),
                $updatedOrchestrations
            );
        }

        return $updatedOrchestrations;
    }

    private function assertOrchestration($updatedOrchestrations, $orchestration, $oldComponentId, $newComponentId)
    {
        $this->assertNotEmpty($updatedOrchestrations);
        $orchestrationIsUpdated = false;
        foreach ($updatedOrchestrations as $updated) {
            // is updated?
            if ($updated['id'] == $orchestration['id']) {
                $orchestrationIsUpdated = true;
            }
            // get tasks
            $tasks = $this->orchestratorService->getTasks($updated['id']);
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
        $affectedOrchestrations = $this->orchestratorService->getOrchestrations($oldComponentId, $newComponentId);
        $this->assertNotEmpty($affectedOrchestrations);
        foreach ($affectedOrchestrations as $affected) {
            if ($affected['id'] == $orchestration['id']) {
                $this->assertTrue($affected['hasNew']);
            }
        }
    }

    public function testStatus()
    {
        $migration = new WrGoogleDriveMigration($this->getLogger());
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

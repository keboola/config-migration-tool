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

    /** @var WrGoogleDriveMigration */
    private $migration;

    public function setUp()
    {
        parent::setUp();
        $logger = $this->getLogger();
        $this->orchestratorService = new OrchestratorService($logger);
        $this->migration = new WrGoogleDriveMigration($logger);
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

        $createdConfigurations = $this->migration->execute();

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
            $sapiService = new StorageApiService();
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
                "name" => "Wr Google Drive Migration Test",
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

        // test update orchestration - drive and sheets
        $driveConfiguration = new Configuration();
        $driveConfiguration->setComponentId($driveComponentId);
        $driveConfiguration->setConfigurationId('testing');
        $driveConfiguration->setName('testing');

        $sheetsConfiguration = new Configuration();
        $sheetsConfiguration->setComponentId($sheetsComponentId);
        $sheetsConfiguration->setConfigurationId('testing');
        $sheetsConfiguration->setName('testing');

        $updatedOrchestrations = $this->migration->updateOrchestrations($driveConfiguration, $sheetsConfiguration);
        $this->assertOrchestration($updatedOrchestrations, $orchestration, $oldComponentId, $driveComponentId);
        $this->assertOrchestration($updatedOrchestrations, $orchestration, $oldComponentId, $sheetsComponentId);

        // cleanup
        $this->orchestratorService->request('delete', sprintf('orchestrations/%s', $orchestration['id']));
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
            $newComponentTaskExists = false;
            foreach ($tasks as $task) {
                if (isset($task['component']) && $task['component'] == $newComponentId) {
                    $this->assertNotEmpty($task['actionParameters']['config']);
                    $newComponentTaskExists = true;
                    break;
                }
            }
            $this->assertTrue($newComponentTaskExists);
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
        $this->createOldConfigs();
        $status = $this->migration->status();

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

<?php
/**
 * Author: miro@keboola.com
 * Date: 13/06/2017
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\WrGoogleDriveConfigurator;
use Keboola\ConfigMigrationTool\Configurator\WrGoogleSheetsConfigurator;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\OAuthService;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\ConfigMigrationTool\Service\WrGoogleDriveService;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Options\Components\Configuration;
use Monolog\Logger;

class WrGoogleDriveMigration
{
    /** @var Logger */
    private $logger;

    /** @var WrGoogleDriveConfigurator */
    private $driveConfigurator;

    /** @var WrGoogleSheetsConfigurator */
    private $sheetsConfigurator;

    /** @var OrchestratorService */
    private $orchestratorService;

    /** @var StorageApiService */
    private $sapiService;

    /** @var WrGoogleDriveService */
    private $googleDriveService;

    /** @var OAuthService */
    private $oauthService;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->sapiService = new StorageApiService();
        $this->orchestratorService = new OrchestratorService($this->logger);
        $this->driveConfigurator = new WrGoogleDriveConfigurator();
        $this->sheetsConfigurator = new WrGoogleSheetsConfigurator();
        $this->googleDriveService = new WrGoogleDriveService($this->logger);
        $this->oauthService = new OAuthService();
    }

    public function execute()
    {
        $tables = $this->sapiService->getConfigurationTables('wr-google-drive');
        $createdConfigurations = [];
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] !== 'success') {
                try {
                    // get old Account from old Google Drive Writer API, SAPI configuration
                    $account = $this->googleDriveService->getAccount($attributes['id']);

                    try {
                        $componentCfg = $this->sapiService->getConfiguration('wr-google-drive', $attributes['id']);
                    } catch (ClientException $e) {
                        // orphaned sys bucket
                        if (strstr($e->getMessage(), 'not found') !== false) {
                            continue;
                        }
                        throw $e;
                    }

                    $account['accountNamePretty'] = $componentCfg['name'];

                    // create OAuth credentials
                    $this->oauthService->obtainCredentials('keboola.wr-google-drive', $account);
                    $this->oauthService->obtainCredentials('keboola.wr-google-sheets', $account);

                    // migrate configurations
                    $newDriveConfiguration = $this->toGoogleDrive($account);
                    $newSheetsConfiguration = $this->toGoogleSheets($account);
                    $createdConfigurations[] = $newDriveConfiguration;
                    $createdConfigurations[] = $newSheetsConfiguration;

                    // update orchestration
                    $this->updateOrchestrations($newDriveConfiguration, $newSheetsConfiguration);

                    $this->sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'success');
                } catch (ClientException $e) {
                    $this->sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'error: ' . $e->getMessage());
                    throw new UserException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                        'tableId' => $table['id']
                    ]);
                } catch (\Exception $e) {
                    $this->sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'error: ' . $e->getMessage());
                    throw new ApplicationException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                        'tableId' => $table['id']
                    ]);
                }
            }
        }

        return $createdConfigurations;
    }

    protected function toGoogleDrive($account)
    {
        $account['items'] = array_filter($account['items'], function ($item) {
            return ($item['type'] == 'file' || $item['operation'] == 'create');
        });

        if (!empty($account['items'])) {
            foreach ($account['items'] as $key => &$item) {
                $item['folder'] = $this->getFolder($account['id'], $item);
            }

            $newComponentConfiguration = $this->driveConfigurator->create($account);
            $this->sapiService->createConfiguration($newComponentConfiguration);
            $newComponentConfiguration->setConfiguration($this->driveConfigurator->configure($account));
            $this->sapiService->encryptConfiguration($newComponentConfiguration);
            $this->logger->info(sprintf(
                "Configuration '%s' has been migrated",
                $newComponentConfiguration->getName()
            ));

            return $newComponentConfiguration;
        }

        return null;
    }

    protected function toGoogleSheets($account)
    {
        $account['items'] = array_filter($account['items'], function ($item) {
            return (strtolower($item['type']) == 'sheet' && strtolower($item['operation']) !== 'create');
        });

        if (!empty($account['items'])) {
            // get sheet titles for old sheets
            foreach ($account['items'] as $key => &$item) {
                if (strtolower($item['type']) == 'sheet') {
                    if (empty($item['googleId'])) {
                        unset($account['items'][$key]);
                        continue;
                    }
                    try {
                        // try to get Sheets Title
                        $sheets = $this->googleDriveService->getSheets($account['id'], $item['googleId']);
                        foreach ($sheets as $sheet) {
                            if ($sheet['id'] == $item['sheetId'] || $sheet['wsid'] == $item['sheetId']) {
                                $item['sheetTitle'] = $sheet['title'];
                            }
                        }
                    } catch (\Exception $e) {
                        // sheet not found in account
                        unset($account['items'][$key]);
                        continue;
                    }
                }

                $item['folder'] = $this->getFolder($account['id'], $item);
            }
            
            $newComponentConfiguration = $this->sheetsConfigurator->create($account);
            $this->sapiService->createConfiguration($newComponentConfiguration);
            $newComponentConfiguration->setConfiguration($this->sheetsConfigurator->configure($account));
            $this->sapiService->encryptConfiguration($newComponentConfiguration);
            $this->logger->info(sprintf(
                "Configuration '%s' has been migrated",
                $newComponentConfiguration->getName()
            ));

            return $newComponentConfiguration;
        }

        return null;
    }

    protected function getFolder($accountId, $item)
    {
        $folderId = empty($item['targetFolder']) ? 'root' : $item['targetFolder'];
        $folder = $this->googleDriveService->getRemoteFile($accountId, $folderId);

        return [
            'id' => $folder['id'],
            'title' => $folder['name']
        ];
    }

    public function updateOrchestrations($driveConfiguration, $sheetsConfiguration)
    {
        $oldComponentId = 'wr-google-drive';
        /** @var Configuration $firstConfiguration */
        $firstConfiguration = null;
        /** @var Configuration $secondConfiguration */
        $secondConfiguration = null;
        $updatedOrchestrations = [];

        if ($driveConfiguration == null && $sheetsConfiguration == null) {
            return $updatedOrchestrations;
        }

        if ($driveConfiguration !== null && $sheetsConfiguration !== null) {
            $firstConfiguration = $driveConfiguration;
            $secondConfiguration = $sheetsConfiguration;
        }

        if ($driveConfiguration !== null && $sheetsConfiguration == null) {
            $firstConfiguration = $driveConfiguration;
        }

        if ($sheetsConfiguration !== null && $driveConfiguration == null) {
            $firstConfiguration = $sheetsConfiguration;
        }

        $orchestrations = $this->orchestratorService->listOrchestrations($oldComponentId);

        foreach ($orchestrations as $orchestration) {
            $tasks = $this->orchestratorService->getTasks($orchestration['id']);

            $tasksChanged = false;
            $newTasks = [];
            foreach ($tasks as &$task) {
                $config = $this->orchestratorService->updateTaskConfig(
                    $task,
                    $firstConfiguration->getConfigurationId()
                );

                if ($config !== null) {
                    unset($task['actionParameters']['account']);
                    $task['actionParameters']['config'] = $config;

                    if (isset($task['componentUrl'])
                        && (false !== strstr($task['componentUrl'], '/' . $oldComponentId .'/'))
                    ) {
                        $task['componentUrl'] = str_replace(
                            $oldComponentId,
                            $firstConfiguration->getComponentId(),
                            $task['componentUrl']
                        );
                        $tasksChanged = true;
                    } else if (isset($task['component']) && ($oldComponentId == $task['component'])) {
                        $task['component'] = $firstConfiguration->getComponentId();
                        $tasksChanged = true;
                    }

                    if ($tasksChanged && $secondConfiguration !== null) {
                        $newTask = $task;
                        if (isset($newTask['componentUrl'])) {
                            unset($newTask['componentUrl']);
                        }
                        $newTask['component'] = $secondConfiguration->getComponentId();
                        $newTasks[] = $newTask;
                    }
                }
            }

            if ($tasksChanged) {
                foreach ($newTasks as $newTask) {
                    $tasks[] = $newTask;
                }

                $this->orchestratorService->updateTasks($orchestration['id'], $tasks);
                $updatedOrchestrations[] = $orchestration;
            }
        }

        return $updatedOrchestrations;
    }

    public function status()
    {
        $oldComponentId = 'wr-google-drive';
        $driveComponentId = 'keboola.wr-google-drive';
        $sheetsComponentId = 'keboola.wr-google-sheets';

        $driveOrchestrations = $this->orchestratorService->getOrchestrations($oldComponentId, $driveComponentId);
        $sheetsOrchestrations = $this->orchestratorService->getOrchestrations($oldComponentId, $sheetsComponentId);
        $orchestrations = array_merge($driveOrchestrations, $sheetsOrchestrations);

        $tables = $this->sapiService->getConfigurationTables('wr-google-drive');
        $configurations = array_map(
            function ($table) use ($oldComponentId) {
                $attributes = TableHelper::formatAttributes($table['attributes']);
                return [
                    'configId' => $attributes['id'],
                    'configName' => $attributes['name'],
                    'componentId' => $oldComponentId,
                    'tableId' => $table['id'],
                    'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
                ];
            },
            $tables
        );

        return [
            'configurations' => $configurations,
            'orchestrations' => $orchestrations
        ];
    }
}

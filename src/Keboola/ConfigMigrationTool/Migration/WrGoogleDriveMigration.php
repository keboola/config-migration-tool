<?php

declare(strict_types=1);

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
        $this->orchestratorService = new OrchestratorService();
        $this->driveConfigurator = new WrGoogleDriveConfigurator();
        $this->sheetsConfigurator = new WrGoogleSheetsConfigurator();
        $this->googleDriveService = new WrGoogleDriveService();
        $this->oauthService = new OAuthService();
    }

    public function execute() : array
    {
        $tables = $this->sapiService->getConfigurationTables('wr-google-drive');
        $createdConfigurations = [];
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (isset($attributes['migrationStatus']) && $attributes['migrationStatus'] === 'success') {
                continue;
            }

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
                    'tableId' => $table['id'],
                ]);
            } catch (\Throwable $e) {
                $this->sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'error: ' . $e->getMessage());
                throw new ApplicationException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                    'tableId' => $table['id'],
                ]);
            }
        }

        return $createdConfigurations;
    }

    protected function toGoogleDrive(array $account) : ?Configuration
    {
        $driveItems = array_filter($account['items'], function ($item) {
            return ($item['type'] == 'file' || $item['operation'] == 'create');
        });

        if (!empty($driveItems)) {
            foreach ($driveItems as $key => &$item) {
                $item['folder'] = $this->getFolder($account['id'], $item);
            }

            $account['items'] = $driveItems;

            $newComponentConfiguration = $this->driveConfigurator->create($account);
            $this->sapiService->createConfiguration($newComponentConfiguration);
            $newComponentConfiguration->setConfiguration($this->driveConfigurator->configure($account));
            $this->sapiService->encryptConfiguration($newComponentConfiguration);
            $this->logger->info(sprintf(
                "Configuration '%s' files has been migrated",
                $newComponentConfiguration->getName()
            ));

            return $newComponentConfiguration;
        }

        return null;
    }

    protected function toGoogleSheets(array $account) : ?Configuration
    {
        $sheetItems = array_filter($account['items'], function ($item) {
            return (strtolower($item['type']) == 'sheet' && strtolower($item['operation']) !== 'create');
        });

        if (!empty($sheetItems)) {
            // get sheet titles for old sheets
            foreach ($sheetItems as $key => &$item) {
                if (empty($item['googleId'])) {
                    unset($sheetItems[$key]);
                    continue;
                }
                try {
                    // try to get Sheets Title
                    $sheets = $this->googleDriveService->getSheets($account['id'], $item['googleId']);
                    foreach ($sheets as $sheet) {
                        if ($sheet['id'] == $item['sheetId'] || $sheet['wsid'] == $item['sheetId']) {
                            $item['sheetId'] = $sheet['id'];
                            $item['sheetTitle'] = $sheet['title'];
                        }
                    }
                    $item['folder'] = $this->getFolder($account['id'], $item);
                } catch (\Throwable $e) {
                    // sheet not found in account
                    unset($sheetItems[$key]);
                    continue;
                }
            }

            $account['items'] = $sheetItems;
            
            $newComponentConfiguration = $this->sheetsConfigurator->create($account);
            $this->sapiService->createConfiguration($newComponentConfiguration);
            $newComponentConfiguration->setConfiguration($this->sheetsConfigurator->configure($account));
            $this->sapiService->encryptConfiguration($newComponentConfiguration);
            $this->logger->info(sprintf(
                "Configuration '%s' sheets has been migrated",
                $newComponentConfiguration->getName()
            ));

            return $newComponentConfiguration;
        }

        return null;
    }

    protected function getFolder(string $accountId, array $item) : array
    {
        $folderId = empty($item['targetFolder']) ? 'root' : $item['targetFolder'];
        $folder = $this->googleDriveService->getRemoteFile($accountId, $folderId);

        return [
            'id' => $folder['id'],
            'title' => $folder['name'],
        ];
    }

    public function updateOrchestrations(?Configuration $driveConfiguration, ?Configuration $sheetsConfiguration) : array
    {
        $oldComponentId = 'wr-google-drive';
        /** @var Configuration $firstConfiguration */
        $firstConfiguration = null;
        /** @var ?Configuration $secondConfiguration */
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
            $tasks = $this->orchestratorService->getTasks((string)$orchestration['id']);

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

    public function status() : array
    {
        $oldComponentId = 'wr-google-drive';
        $driveComponentId = 'keboola.wr-google-drive';
        $sheetsComponentId = 'keboola.wr-google-sheets';

        $driveOrchestrations = $this->orchestratorService->getOrchestrations($oldComponentId, $driveComponentId);
        $sheetsOrchestrations = $this->orchestratorService->getOrchestrations($oldComponentId, $sheetsComponentId);
        $orchestrations = array_merge($driveOrchestrations, $sheetsOrchestrations);

        $tables = $this->sapiService->getConfigurationTables('wr-google-drive');

        $configurations = array_filter(array_map(
            function ($table) use ($oldComponentId) {
                $attributes = TableHelper::formatAttributes($table['attributes']);
                if (empty($attributes['id'])) {
                    return null;
                }
                return [
                    'configId' => $attributes['id'],
                    'configName' => $attributes['name'],
                    'componentId' => $oldComponentId,
                    'tableId' => $table['id'],
                    'status' => isset($attributes['migrationStatus']) ? $attributes['migrationStatus'] : 'n/a',
                ];
            },
            $tables
        ));

        return [
            'configurations' => $configurations,
            'orchestrations' => $orchestrations,
        ];
    }
}

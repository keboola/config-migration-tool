<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 16/05/16
 * Time: 15:13
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\ExDbConfigurator;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\ExDbService;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;
use Monolog\Logger;

class ExDbMigration implements MigrationInterface
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function execute()
    {
        $sapiService = new StorageApiService();
        $orchestratorService = new OrchestratorService($this->logger);
        $configurator = new ExDbConfigurator();
        $exDbService = new ExDbService($this->logger);
//        $tables = $sapiService->getConfigurationTables('ex-db');

        $oldDbConfigs = $exDbService->getConfigs();

        $createdConfigurations = [];
        foreach ($oldDbConfigs as $oldConfig) {
            $sysTableId = 'sys.c-ex-db.' . $oldConfig['id'];
            $sysTable = $sapiService->getClient()->getTable($sysTableId);
            $attributes = TableHelper::formatAttributes($sysTable['attributes']);

            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                try {
                    $credentials = $exDbService->getCredentials($oldConfig['id']);
                    $queries = $exDbService->getQueries($oldConfig['id']);
                    $componentCfg = $sapiService->getConfiguration('ex-db', $attributes['accountId']);

                    $configuration = $configurator->create($attributes, $componentCfg['name']);
                    $sapiService->createConfiguration($configuration);
                    $configuration->setConfiguration($configurator->configure($credentials, $queries));
                    $sapiService->encryptConfiguration($configuration);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $orchestratorService->updateOrchestrations('ex-db', $configuration);

                    $this->logger->info(sprintf(
                        "Orchestration task for configuration '%s' has been updated",
                        $configuration->getName()
                    ));

                    $createdConfigurations[] = $configuration;
                    $sapiService->getClient()->setTableAttribute($sysTableId, 'migrationStatus', 'success');
                } catch (ClientException $e) {
                    $sapiService->getClient()->setTableAttribute($sysTableId, 'migrationStatus', 'error: ' . $e->getMessage());
                    throw new UserException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                        'tableId' => $sysTableId
                    ]);
                } catch (\Exception $e) {
                    $sapiService->getClient()->setTableAttribute($sysTableId, 'migrationStatus', 'error: ' . $e->getMessage());
                    throw new ApplicationException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                        'tableId' => $sysTableId
                    ]);
                }
            }
        }

        return $createdConfigurations;
    }

    public function status()
    {
        $sapiService = new StorageApiService();
        $orchestratorService = new OrchestratorService($this->logger);

        $tables = $sapiService->getConfigurationTables('ex-db');
        return [
            'configurations' => array_map(
                function ($item) {
                    $attributes = TableHelper::formatAttributes($item['attributes']);
                    return [
                        'configId' => $attributes['accountId'],
                        'configName' => $attributes['name'],
                        'componentId' => sprintf(
                            'keboola.ex-db-%s',
                            isset($attributes['db.driver'])
                            ?$attributes['db.driver']
                            :'mysql'
                        ),
                        'tableId' => $item['id'],
                        'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
                    ];
                },
                $tables
            ),
            'orchestrations' => $orchestratorService->getOrchestrations('ex-db', 'keboola.ex-db-')
        ];
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 09/01/17
 * Time: 15:59
 */

namespace Keboola\ConfigMigrationTool\Migration;


use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\ConfigMigrationTool\Service\WrDbService;
use Monolog\Logger;

class WrDbMigration
{
    private $logger;

    private $driver;

    public function __construct(Logger $logger, $driver = 'mysql')
    {
        $this->logger = $logger;
        $this->driver = $driver;
    }

    public function execute()
    {
        $sapiService = new StorageApiService();
        $orchestratorService = new OrchestratorService($this->logger);
        $configurator = new WrDbConfigurator();
        $wrDbService = new WrDbService($this->driver, $this->logger);
//        $tables = $sapiService->getConfigurationTables('ex-db');

        $oldDbConfigs = $wrDbService->getConfigs();

        $createdConfigurations = [];
        foreach ($oldDbConfigs as $oldConfig) {
            $sysTableId = 'sys.c-wr-db.' . $oldConfig['id'];
            $sysTable = $sapiService->getClient()->getTable($sysTableId);
            $attributes = TableHelper::formatAttributes($sysTable['attributes']);

            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                try {
                    $credentials = $wrDbService->getCredentials($oldConfig['id']);
                    $queries = $wrDbService->getQueries($oldConfig['id']);
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
                            'keboola.wr-db-%s',
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
            'orchestrations' => $orchestratorService->getOrchestrations('wr-db', 'keboola.wr-db-')
        ];
    }
}

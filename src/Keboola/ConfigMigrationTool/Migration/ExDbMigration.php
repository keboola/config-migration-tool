<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 16/05/16
 * Time: 15:13
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\ExDbConfigurator;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
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
        $orchestratorService = new OrchestratorService();
        $configurator = new ExDbConfigurator();
        $tables = $sapiService->getConfigurationTables('ex-db');

        $createdConfigurations = [];
        foreach ($tables as $table) {
            $attributes = TableHelper::formatAttributes($table['attributes']);
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                try {
                    $tableData = $sapiService->exportTable($table['id']);

                    $configuration = $configurator->create($attributes);
                    $sapiService->createConfiguration($configuration);
                    $configuration->setConfiguration($configurator->configure($attributes, $tableData));
                    $sapiService->encryptConfiguration($configuration);

                    $orchestratorService->updateOrchestrations('ex-db', $configuration->getComponentId());

                    $createdConfigurations[] = $configuration;
                    $sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'success');
                } catch (\Exception $e) {
                    $sapiService->getClient()->setTableAttribute($table['id'], 'migrationStatus', 'error');
                    $this->logger->error("Error occured during migration", ['message' => $e->getMessage()]);
                }
            }
        }

        return $createdConfigurations;
    }

    public function status()
    {
        $sapiService = new StorageApiService();
        $orchestratorService = new OrchestratorService();

        $tables = $sapiService->getConfigurationTables('ex-db');
        return [
            'configurations' => array_map(
                function ($item) {
                    $attributes = TableHelper::formatAttributes($item['attributes']);
                    return [
                        'configId' => $attributes['accountId'],
                        'configName' => $attributes['name'],
                        'componentId' => sprintf('keboola.ex-db-%s', $attributes['db.driver']),
                        'tableId' => $item['id'],
                        'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
                    ];
                },
                $tables
            ),
            'orchestrations' => $orchestratorService->getAffectedOrchestrations('ex-db')
        ];
    }
}

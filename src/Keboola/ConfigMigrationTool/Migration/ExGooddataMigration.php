<?php
/**
 * @copy Keboola 2016
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\ExGoodDataConfigurator;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\ExGoodDataService;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;
use Monolog\Logger;

class ExGooddataMigration implements MigrationInterface
{
    private $logger;
    private $sapiService;
    private $orchestratorService;
    private $configurator;
    private $service;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->sapiService = new StorageApiService();
        $this->orchestratorService = new OrchestratorService($this->logger);
        $this->configurator = new ExGoodDataConfigurator();
        $this->service = new ExGoodDataService();
    }

    public function setService(ExGoodDataService $service)
    {
        $this->service = $service;
    }

    public function execute()
    {
        $createdConfigurations = [];
        $writers = $this->service->getProjectsWriters();
        foreach ($this->service->getConfigs() as $oldConfig) {
            if (!isset($oldConfig['id'])) {
                throw new UserException("One of the configuration tables does not contain attribute id");
            }
            $sysTableId = 'sys.c-ex-gooddata.' . $oldConfig['id'];
            $sysTable = $this->sapiService->getClient()->getTable($sysTableId);
            $attributes = TableHelper::formatAttributes($sysTable['attributes']);

            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                try {
                    $reports = $this->service->getReports($oldConfig['id']);

                    $configuration = $this->configurator->create($oldConfig);
                    $this->sapiService->createConfiguration($configuration);
                    $configuration->setConfiguration($this->configurator->configure($writers, $reports));
                    $this->sapiService->encryptConfiguration($configuration);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $this->orchestratorService->updateOrchestrations('ex-gooddata', $configuration);

                    $this->logger->info(sprintf(
                        "Orchestration task for configuration '%s' has been updated",
                        $configuration->getName()
                    ));

                    $createdConfigurations[] = $configuration;
                    $this->sapiService->getClient()->setTableAttribute($sysTableId, 'migrationStatus', 'success');
                } catch (\Exception $e) {
                    $this->sapiService->getClient()->setTableAttribute($sysTableId, 'migrationStatus', "error: {$e->getMessage()}");
                    try {
                        $this->sapiService->deleteConfiguration('keboola.ex-gooddata', $oldConfig['id']);
                    } catch (ClientException $e2) {
                        // Ignore
                    }
                    if ($e instanceof ClientException || $e instanceof UserException) {
                        throw new UserException($e->getMessage(), 400, $e, [
                            'tableId' => $sysTableId
                        ]);
                    }

                    throw new ApplicationException($e->getMessage(), 500, $e, [
                        'tableId' => $sysTableId
                    ]);
                }
            }
        }

        return $createdConfigurations;
    }

    public function status()
    {
        $tables = $this->sapiService->getConfigurationTables('ex-gooddata');
        return [
            'configurations' => array_map(
                function ($item) {
                    $attributes = TableHelper::formatAttributes($item['attributes']);
                    return [
                        'configId' => $attributes['id'],
                        'configName' => isset($attributes['name']) ? $attributes['name'] : $attributes['id'],
                        'componentId' => 'keboola.ex-gooddata',
                        'tableId' => $item['id'],
                        'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
                    ];
                },
                $tables
            ),
            'orchestrations' => $this->orchestratorService->getOrchestrations('ex-gooddata', 'keboola.ex-gooddata-')
        ];
    }
}

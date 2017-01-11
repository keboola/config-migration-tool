<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 09/01/17
 * Time: 15:59
 */

namespace Keboola\ConfigMigrationTool\Migration;


use Keboola\ConfigMigrationTool\Configurator\WrDbConfigurator;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\ConfigMigrationTool\Service\WrDbService;
use Keboola\StorageApi\ClientException;
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

        $oldDbConfigs = $wrDbService->getConfigs();

        $createdConfigurations = [];
        foreach ($oldDbConfigs as $oldConfig) {
            $sysBucketId = sprintf('sys.c-wr-db-%s-%s', $this->driver, $oldConfig['id']);
            $sysBucket = $sapiService->getClient()->getBucket($sysBucketId);
            $attributes = TableHelper::formatAttributes($sysBucket['attributes']);

            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] !== 'success') {
                try {
                    $credentials = $wrDbService->getCredentials($oldConfig['id']);
                    $configTables = $wrDbService->getConfigTables($oldConfig['id']);
                    $componentCfg = $sapiService->getConfiguration(
                        sprintf('wr-db-%s', $this->driver),
                        $attributes['writerId']
                    );

                    $configuration = $configurator->create($attributes, $componentCfg['name']);
                    $sapiService->createConfiguration($configuration);
                    $configuration->setConfiguration($configurator->configure($credentials, $configTables));
                    $sapiService->encryptConfiguration($configuration);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $orchestratorService->updateOrchestrations(sprintf('wr-db-%s', $this->driver), $configuration);

                    $this->logger->info(sprintf(
                        "Orchestration task for configuration '%s' has been updated",
                        $configuration->getName()
                    ));

                    $createdConfigurations[] = $configuration;
                    $sapiService->getClient()->setBucketAttribute($sysBucketId, 'migrationStatus', 'success');
                } catch (ClientException $e) {
                    $sapiService->getClient()->setBucketAttribute($sysBucketId, 'migrationStatus', 'error: ' . $e->getMessage());
                    throw new UserException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                        'bucketId' => $sysBucketId
                    ]);
                } catch (\Exception $e) {
                    $sapiService->getClient()->setBucketAttribute($sysBucketId, 'migrationStatus', 'error: ' . $e->getMessage());
                    throw new ApplicationException("Error occured during migration: " . $e->getMessage(), 500, $e, [
                        'bucketId' => $sysBucketId
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
        $oldComponentId = sprintf('wr-db-%s', $this->driver);
        $newComponentId = sprintf('keboola.wr-db-%s', $this->driver);

        $buckets = $sapiService->getConfigurationBuckets($oldComponentId);
        return [
            'configurations' => array_map(
                function ($item) use ($oldComponentId, $newComponentId) {
                    $attributes = TableHelper::formatAttributes($item['attributes']);
                    return [
                        'configId' => $attributes['id'],
                        'configName' => $attributes['name'],
                        'componentId' => $newComponentId,
                        'tableId' => $item['id'],
                        'status' => isset($attributes['migrationStatus'])?$attributes['migrationStatus']:'n/a'
                    ];
                },
                $buckets
            ),
            'orchestrations' => $orchestratorService->getOrchestrations($oldComponentId, $newComponentId)
        ];
    }
}

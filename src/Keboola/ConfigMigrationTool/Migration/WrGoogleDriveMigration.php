<?php
/**
 * Author: miro@keboola.com
 * Date: 13/06/2017
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Configurator\WrGoogleDriveConfigurator;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Helper\TableHelper;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\ConfigMigrationTool\Service\WrGoogleDriveService;
use Keboola\StorageApi\ClientException;
use Monolog\Logger;

class WrGoogleDriveMigration
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
        $driveConfigurator = new WrGoogleDriveConfigurator();
        $googleDriveService = new WrGoogleDriveService($this->logger);

        $oldDbConfigs = $googleDriveService->getConfigs();

        $createdConfigurations = [];
        foreach ($oldDbConfigs as $oldConfig) {
            $sysBucketId = 'sys.c-wr-google-drive';
            $sysBucket = $sapiService->getClient()->getBucket($sysBucketId);
            $attributes = TableHelper::formatAttributes($sysBucket['attributes']);

            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] !== 'success') {
                try {
                    $account = $googleDriveService->getAccount($oldConfig['id']);
                    $componentCfg = $sapiService->getConfiguration(
                        'wr-google-drive',
                        $attributes['id']
                    );
                    $account['accountNamePretty'] = $componentCfg['name'];

                    $configuration = $driveConfigurator->create($account);
                    $sapiService->createConfiguration($configuration);
                    $configuration->setConfiguration($driveConfigurator->configure($account));
                    $sapiService->encryptConfiguration($configuration);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $orchestratorService->updateOrchestrations('wr-google-drive', $configuration);

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
        $oldComponentId = 'wr-google-drive';
        $newComponentId = 'keboola.wr-google-drive';

        $buckets = $sapiService->getConfigurationBuckets($oldComponentId);
        return [
            'configurations' => array_map(
                function ($item) use ($oldComponentId) {
                    $attributes = TableHelper::formatAttributes($item['attributes']);
                    return [
                        'configId' => $attributes['writerId'],
                        'configName' => $attributes['writerId'],
                        'componentId' => $oldComponentId,
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

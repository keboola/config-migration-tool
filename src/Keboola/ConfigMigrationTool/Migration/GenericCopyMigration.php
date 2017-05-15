<?php
/**
 * @copy Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;

class GenericCopyMigration extends VersionMigration
{

    public function execute()
    {
        return $this->doExecute();
    }

    /**
     * @param callable|null $configurationAdjustment Optional callback to adjust configuration object before saving
     * @return array
     * @throws ApplicationException
     * @throws UserException
     */
    protected function doExecute(callable $configurationAdjustment = null)
    {
        $createdConfigurations = [];
        foreach ($this->storageApiService->getConfigurations($this->oldComponentId) as $oldConfig) {
            if (!isset($attributes['migrationStatus']) || $attributes['migrationStatus'] != 'success') {
                try {
                    $configuration = $this->buildConfigurationObject($this->newComponentId, $oldConfig);
                    $this->storageApiService->createConfiguration($configuration);
                    if ($configurationAdjustment) {
                        $configuration = $configurationAdjustment($configuration);
                    }
                    $this->storageApiService->encryptConfiguration($configuration);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $this->orchestratorService->updateOrchestrations($this->oldComponentId, $configuration);

                    $this->logger->info(sprintf(
                        "Orchestration task for configuration '%s' has been updated",
                        $configuration->getName()
                    ));

                    $createdConfigurations[] = $configuration;
                    $oldConfiguration = $this->buildConfigurationObject($this->oldComponentId, $oldConfig);
                    $this->saveConfigurationOptions($oldConfiguration, ['migrationStatus' => 'success']);
                } catch (\Exception $e) {
                    $oldConfiguration = $this->buildConfigurationObject($this->oldComponentId, $oldConfig);
                    $this->saveConfigurationOptions(
                        $oldConfiguration,
                        ['migrationStatus' => "error: {$e->getMessage()}"]
                    );
                    $this->storageApiService->deleteConfiguration($this->newComponentId, $oldConfig['id']);
                    if ($e instanceof ClientException || $e instanceof UserException) {
                        throw new UserException($e->getMessage(), 400, $e, [
                            'oldComponentId' => $this->oldComponentId,
                            'newComponentId' => $this->newComponentId,
                            'configurationId' => $oldConfig['id']
                        ]);
                    }

                    throw new ApplicationException($e->getMessage(), 500, $e, [
                        'oldComponentId' => $this->oldComponentId,
                        'newComponentId' => $this->newComponentId,
                        'configurationId' => $oldConfig['id']
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

        $configurations = $sapiService->getConfigurations($this->oldComponentId);
        return [
            'configurations' => array_map(
                function ($item) {
                    return [
                        'configId' => $item['id'],
                        'configName' => $item['name'],
                        'componentId' => $this->oldComponentId,
                        'status' => isset($item['configuration']['migrationStatus'])
                            ? $item['configuration']['migrationStatus'] : 'n/a'
                    ];
                },
                $configurations
            ),
            'orchestrations' => $orchestratorService->getOrchestrations($this->oldComponentId, $this->newComponentId)
        ];
    }
}

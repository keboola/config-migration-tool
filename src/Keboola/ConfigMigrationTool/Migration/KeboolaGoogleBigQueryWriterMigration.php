<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Service\GoodDataProvisioningService;
use Keboola\ConfigMigrationTool\Service\GoodDataService;
use Keboola\ConfigMigrationTool\Service\LegacyGoodDataWriterService;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\StorageApi\ClientException;

class KeboolaGoogleBigQueryWriterMigration extends GenericCopyMigration
{
    public function execute(): array
    {
        return $this->doExecute();
    }

    /**
     * @param callable|null $migrationHook Optional callback to adjust configuration object before saving
     * @return array
     * @throws ApplicationException
     * @throws UserException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function doExecute(?callable $migrationHook = null): array
    {
        $createdConfigurations = [];
        foreach ($this->storageApiService->getConfigurations($this->originComponentId) as $oldConfig) {
            if (!isset($oldConfig['configuration']['migrationStatus'])
                || $oldConfig['configuration']['migrationStatus'] != 'success') {
                try {
                    $newConfig = $this->transformConfiguration($oldConfig);
                    $configuration = $this->buildConfigurationObject($this->destinationComponentId, $newConfig);

                    $this->storageApiService->createConfiguration($configuration);
                    $this->storageApiService->encryptAndSaveConfiguration($configuration);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $this->orchestratorService->updateOrchestrations($this->originComponentId, $configuration);

                    $this->logger->info(sprintf(
                        "Orchestration task for configuration '%s' has been updated",
                        $configuration->getName()
                    ));

                    $createdConfigurations[] = $configuration;
                    $oldConfiguration = $this->buildConfigurationObject($this->originComponentId, $oldConfig);
                    $this->saveConfigurationOptions($oldConfiguration, ['migrationStatus' => 'success']);
                } catch (\Throwable $e) {
                    $oldConfiguration = $this->buildConfigurationObject($this->originComponentId, $oldConfig);
                    $this->saveConfigurationOptions(
                        $oldConfiguration,
                        ['migrationStatus' => "error: {$e->getMessage()}"]
                    );
                    try {
                        $this->storageApiService->deleteConfiguration($this->destinationComponentId, $oldConfig['id']);
                    } catch (ClientException $e2) {
                        // Ignore
                    }
                    if ($e instanceof ClientException || $e instanceof UserException) {
                        $this->logger->warn("Migration of configuration {$oldConfig['id']} skipped because of an error: {$e->getMessage()}");
                    } else {
                        throw new ApplicationException($e->getMessage(), 500, $e, [
                            'oldComponentId' => $this->originComponentId,
                            'newComponentId' => $this->destinationComponentId,
                            'configurationId' => $oldConfig['id'],
                        ]);
                    }
                }
            }
        }
        return $createdConfigurations;
    }

    public function transformConfiguration(array $oldConfig): array
    {
        $newConfig = $oldConfig;
        if (isset($newConfig["configuration"]["authorization"])) {
            unset($newConfig["configuration"]["authorization"]);
        }
        if (isset($newConfig["configuration"]["parameters"]["project"])) {
            unset($newConfig["configuration"]["parameters"]["project"]);
        }
        return $newConfig;
    }
}

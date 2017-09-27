<?php
/**
 * @package config-migration-tool
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Options\Components\Configuration;

class KeboolaGoodDataWriterMigration extends GenericCopyMigration
{
    public function execute()
    {
        return $this->doExecute();
    }

    /**
     * @param callable|null $migrationHook Optional callback to adjust configuration object before saving
     * @return array
     */
    protected function doExecute(callable $migrationHook = null)
    {
        $createdConfigurations = [];
        foreach ($this->storageApiService->getConfigurations($this->originComponentId) as $oldConfig) {
            if (!isset($oldConfig['configuration']['migrationStatus'])
                || $oldConfig['configuration']['migrationStatus'] != 'success') {
                try {
                    $newConfig = $oldConfig;
                    unset($newConfig['migrationStatus']);
                    unset($newConfig['configuration']['filters']);
                    if (!empty($newConfig['configuration']['domain']['url'])) {
                        $newConfig['configuration']['project']['isWhiteLabel'] = true;
                    }
                    unset($newConfig['configuration']['domain']);
                    if (isset($newConfig['configuration']['user']['password'])) {
                        $newConfig['configuration']['user']['#password'] = $newConfig['configuration']['user']['password'];
                        unset($newConfig['configuration']['user']['password']);
                    }
                    unset($newConfig['configuration']['filterColumn']);
                    unset($newConfig['configuration']['addDatasetTitleToColumns']);
                    if (isset($newConfig['configuration']['dimensions'])) {
                        foreach ($newConfig['configuration']['dimensions'] as $dimensionId => $dimension) {
                            unset($newConfig['configuration']['dimensions'][$dimensionId]['isExported']);
                        }
                    }
                    if (!empty($oldConfig['rows'])) {
                        foreach ($oldConfig['rows'] as $r) {
                            unset($r['configuration']['export']);
                            unset($r['configuration']['isExported']);
                            $newConfig['configuration']['tables'][$r['id']] = $r['configuration'];
                        }
                        $newConfig['rows'] = [];
                    }
                    $configuration = $this->buildConfigurationObject($this->destinationComponentId, $newConfig);

                    $this->storageApiService->createConfiguration($configuration);
                    $this->storageApiService->encryptConfiguration($configuration);


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
                } catch (\Exception $e) {
                    $oldConfiguration = $this->buildConfigurationObject($this->originComponentId, $oldConfig);
                    $this->saveConfigurationOptions(
                        $oldConfiguration,
                        ['migrationStatus' => "error: {$e->getMessage()}"]
                    );
                    $this->storageApiService->deleteConfiguration($this->destinationComponentId, $oldConfig['id']);
                    if ($e instanceof ClientException || $e instanceof UserException) {
                        throw new UserException($e->getMessage(), 400, $e, [
                            'oldComponentId' => $this->originComponentId,
                            'newComponentId' => $this->destinationComponentId,
                            'configurationId' => $oldConfig['id']
                        ]);
                    }

                    throw new ApplicationException($e->getMessage(), 500, $e, [
                        'oldComponentId' => $this->originComponentId,
                        'newComponentId' => $this->destinationComponentId,
                        'configurationId' => $oldConfig['id']
                    ]);
                }
            }
        }
        return $createdConfigurations;
    }
}

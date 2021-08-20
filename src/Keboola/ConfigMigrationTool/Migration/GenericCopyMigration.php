<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Options\Components\Configuration;

class GenericCopyMigration extends DockerAppMigration
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
     */
    protected function doExecute(?callable $migrationHook = null): array
    {
        $createdConfigurations = [];
        foreach ($this->storageApiService->getConfigurations($this->originComponentId) as $oldConfig) {
            if (!$this->isConfigurationMigrated($oldConfig)) {
                try {
                    $configuration = $this->buildConfigurationObject($this->destinationComponentId, $oldConfig);
                    if ($migrationHook) {
                        $configuration = $migrationHook($configuration);
                    }
                    $c = $configuration->getConfiguration();
                    unset($c['authorization']);
                    $configuration->setConfiguration($c);

                    $configuration->setConfiguration($this->storageApiService->encryptConfiguration($configuration));
                    $this->storageApiService->createConfiguration($configuration);

                    $this->processConfigRows($configuration, $oldConfig);

                    $this->logger->info(sprintf(
                        "Configuration '%s' has been migrated",
                        $configuration->getName()
                    ));

                    $createdConfigurations[] = $configuration;
                    $oldConfiguration = $this->buildConfigurationObject($this->originComponentId, $oldConfig);
                    $this->saveConfigurationOptions($oldConfiguration, ['runtime' => ['migrationStatus' => 'success']]);
                } catch (\Throwable $e) {
                    $oldConfiguration = $this->buildConfigurationObject($this->originComponentId, $oldConfig);
                    $this->saveConfigurationOptions(
                        $oldConfiguration,
                        ['runtime' => ['migrationStatus' => "error: {$e->getMessage()}"]]
                    );
                    $this->storageApiService->deleteConfiguration($this->destinationComponentId, $oldConfig['id']);
                    if ($e instanceof ClientException || $e instanceof UserException) {
                        throw new UserException($e->getMessage(), 400, $e, [
                            'oldComponentId' => $this->originComponentId,
                            'newComponentId' => $this->destinationComponentId,
                            'configurationId' => $oldConfig['id'],
                        ]);
                    }

                    throw new ApplicationException($e->getMessage(), 500, $e, [
                        'oldComponentId' => $this->originComponentId,
                        'newComponentId' => $this->destinationComponentId,
                        'configurationId' => $oldConfig['id'],
                    ]);
                }
            }
        }
        return $createdConfigurations;
    }

    public function status(): array
    {
        $sapiService = new StorageApiService();

        $configurations = $sapiService->getConfigurations($this->originComponentId);
        return [
            'configurations' => array_map(
                function ($item) {
                    return [
                        'configId' => $item['id'],
                        'configName' => $item['name'],
                        'componentId' => $this->originComponentId,
                        'status' => $this->getConfigurationStatus($item),
                    ];
                },
                $configurations
            ),
        ];
    }

    protected function processConfigRows(Configuration $configuration, array $oldConfig): void
    {
        if (!empty($oldConfig['rows'])) {
            foreach ($oldConfig['rows'] as $r) {
                $this->storageApiService->addConfigurationRow($configuration, $r['id'], $r['configuration']);
            }
        }
    }
}

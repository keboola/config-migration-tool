<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;

class GenericCopyMigrationWithRows extends DockerAppMigration
{
    public function execute(): array
    {
        return $this->doExecute();
    }

    /**
     * @return array
     * @throws ApplicationException
     * @throws UserException
     */
    protected function doExecute(): array
    {
        $createdConfigurations = [];
        foreach ($this->storageApiService->getConfigurations($this->originComponentId) as $oldConfig) {
            if (!isset($oldConfig['configuration']['migrationStatus'])
                || $oldConfig['configuration']['migrationStatus'] != 'success') {
                try {
                    $configuration = $this->buildConfigurationObject($this->destinationComponentId, $oldConfig);
                    $configurationRows = $this->buildConfigurationRowObjects($configuration, $oldConfig['rows']);
                    $migratedResult = $this->transformConfiguration($configuration, $configurationRows);
                    $configuration = $migratedResult['configuration'];
                    $configurationRows = $migratedResult['rows'];

                    $c = $configuration->getConfiguration();
                    unset($c['authorization']);
                    $configuration->setConfiguration($c);

                    $configuration->setConfiguration($this->storageApiService->encryptConfiguration($configuration));
                    $this->storageApiService->createConfiguration($configuration);

                    if (!empty($configurationRows)) {
                        foreach ($configurationRows as $r) {
                            $this->storageApiService
                                ->addConfigurationRowObject($r);
                        }
                    }

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

    /**
     * @param Configuration $configuration
     * @param array $rowConfigurations
     * @return array - ["configuration" => Configuration $configuration, "rows" => array $rowConfigurations]
     */
    protected function transformConfiguration(Configuration $configuration, array $rowConfigurations): array
    {
        return ["configuration" => $configuration, "rows" => $rowConfigurations];
    }

    protected function buildConfigurationObject(string $componentId, array $config): Configuration
    {
        $configuration = new Configuration();
        $configuration->setComponentId($componentId);
        $configuration->setConfigurationId($config['id']);
        $configuration->setName($config['name']);
        $configuration->setDescription($config['description']);
        $configuration->setConfiguration($config['configuration']);
        $configuration->setRowsSortOrder([]);
        $configuration->setState($config['state']);
        return $configuration;
    }

    protected function buildConfigurationRowObjects(Configuration $configurationObject, array $rows): array
    {
        $row_objects = [];
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $rowObject = new ConfigurationRow($configurationObject);

                $rowObject->setRowId($r['id']);
                $rowObject->setConfiguration($r['configuration']);
                $rowObject->setName($r['name']);
                $rowObject->setDescription($r['description']);
                $rowObject->setIsDisabled($r['isDisabled']);
                $rowObject->setState($r['state']);
                $rowObject->setState($r['state']);
                $row_objects[] = $rowObject;
            }
            return $row_objects;
        }
    }

    public function status(): array
    {
        $sapiService = new StorageApiService();

        $orchestratorUrl = $sapiService->getServiceUrl(StorageApiService::SYRUP_SERVICE) . '/orchestrator/';
        $orchestratorService = new OrchestratorService($orchestratorUrl);

        $configurations = $sapiService->getConfigurations($this->originComponentId);
        return [
            'configurations' => array_map(
                function ($item) {
                    return [
                        'configId' => $item['id'],
                        'configName' => $item['name'],
                        'componentId' => $this->originComponentId,
                        'status' => isset($item['configuration']['migrationStatus'])
                            ? $item['configuration']['migrationStatus'] : 'n/a',
                    ];
                },
                $configurations
            ),
            'orchestrations' => $orchestratorService->getOrchestrations($this->originComponentId, $this->destinationComponentId),
        ];
    }
}

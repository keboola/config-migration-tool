<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Service\GoodDataProvisioningService;
use Keboola\ConfigMigrationTool\Service\GoodDataService;
use Keboola\ConfigMigrationTool\Service\LegacyGoodDataWriterService;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\StorageApi\ClientException;

class KeboolaGoodDataWriterMigration extends GenericCopyMigration
{
    /** @var GoodDataProvisioningService */
    protected $provisioning;

    /** @var LegacyGoodDataWriterService */
    protected $legacyWriter;

    /** @var GoodDataService */
    protected $goodData;

    /** @var \Keboola\ManageApi\Client */
    protected $manageApi;

    public function execute(): array
    {
        return $this->doExecute();
    }

    public function setProvisioning(GoodDataProvisioningService $provisioning): void
    {
        $this->provisioning = $provisioning;
    }

    public function setLegacyWriter(LegacyGoodDataWriterService $legacyWriter): void
    {
        $this->legacyWriter = $legacyWriter;
    }

    public function setGoodData(GoodDataService $goodData): void
    {
        $this->goodData = $goodData;
    }

    public function setManageApi(\Keboola\ManageApi\Client $manageApi): void
    {
        $this->manageApi = $manageApi;
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
        $pidsForExtractor = [];
        $createdConfigurations = [];
        foreach ($this->storageApiService->getConfigurations($this->originComponentId) as $oldConfig) {
            if (!isset($oldConfig['configuration']['migrationStatus'])
                || $oldConfig['configuration']['migrationStatus'] != 'success') {
                try {
                    $newConfig = $this->transformConfiguration($oldConfig);
                    $this->checkGoodDataConfiguration($newConfig);
                    $this->addProjectToProvisioning($this->provisioning, $newConfig);
                    $this->addUsersToProvisioning($this->provisioning, $this->legacyWriter, $newConfig);

                    $pidsForExtractor[$newConfig['id']] = $newConfig['configuration']['parameters']['project']['pid'];

                    $configuration = $this->buildConfigurationObject($this->destinationComponentId, $newConfig);
                    $configuration->setConfiguration($this->storageApiService->encryptConfiguration($configuration));
                    $this->storageApiService->createConfiguration($configuration);

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
                    $oldConfiguration->setChangeDescription('migration status updated');
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

        foreach ($this->storageApiService->getConfigurations('keboola.ex-gooddata') as $config) {
            if (!empty($config['configuration']['parameters']['writer_id'])) {
                $writerId = $config['configuration']['parameters']['writer_id'];
                if (!isset($pidsForExtractor[$writerId])) {
                    $this->logger->warn("Migration of extractor {$config['id']} skipped because it has writer_id = '{$writerId}' which was not found between Writer configurations.");
                    continue;
                }

                $config['configuration']['parameters']['pid'] = $pidsForExtractor[$writerId];
                unset($config['configuration']['parameters']['writer_id']);

                $configuration = $this->buildConfigurationObject('keboola.ex-gooddata', $config);
                $this->storageApiService->saveConfiguration($configuration);

                $this->logger->info("Configuration of GoodData extractor {$config['id']} updated.");
            }
        }

        return $createdConfigurations;
    }

    public function transformConfiguration(array $oldConfig): array
    {
        $newConfig = $oldConfig;
        $newConfig['configuration'] = [];
        $newConfig['configuration']['parameters'] = $oldConfig['configuration'];
        unset($newConfig['configuration']['parameters']['migrationStatus']);
        unset($newConfig['configuration']['parameters']['filters']);
        if (!empty($newConfig['configuration']['parameters']['domain']['url'])) {
            $newConfig['configuration']['parameters']['project']['backendUrl']
                = $newConfig['configuration']['parameters']['domain']['url'];
        }
        unset($newConfig['configuration']['parameters']['domain']);
        if (!empty($newConfig['configuration']['parameters']['project']['backend'])) {
            $newConfig['configuration']['parameters']['project']['backendUrl']
                = $newConfig['configuration']['parameters']['project']['backend'];
            unset($newConfig['configuration']['parameters']['project']['backend']);
        }
        if (isset($newConfig['configuration']['parameters']['user']['password'])) {
            $newConfig['configuration']['parameters']['user']['#password']
                = $newConfig['configuration']['parameters']['user']['password'];
            unset($newConfig['configuration']['parameters']['user']['password']);
        }
        unset($newConfig['configuration']['parameters']['filterColumn']);
        unset($newConfig['configuration']['parameters']['addDatasetTitleToColumns']);
        if (isset($newConfig['configuration']['parameters']['dimensions'])) {
            foreach ($newConfig['configuration']['parameters']['dimensions'] as $dimensionId => $dim) {
                unset($newConfig['configuration']['parameters']['dimensions'][$dimensionId]['isExported']);
                unset($newConfig['configuration']['parameters']['dimensions'][$dimensionId]['customTemplate']);
                unset($newConfig['configuration']['parameters']['dimensions'][$dimensionId]['title']);
            }
        }
        if (!empty($oldConfig['rows'])) {
            $newConfig['configuration']['storage'] = ['input' => ['tables' => []]];
            foreach ($oldConfig['rows'] as $r) {
                $r['configuration']['columns'] = array_filter($r['configuration']['columns'], function ($column) {
                    return $column['type'] !== 'IGNORE' && $column['type'] !== 'ignore';
                });
                $r['configuration']['columns'] = array_map(function ($column) {
                    unset($column['identifierTime']);
                    return $column;
                }, $r['configuration']['columns']);
                $mapping = [
                    'source' => $r['id'],
                    'columns' => array_keys($r['configuration']['columns']),
                ];
                if (!empty($r['configuration']['incrementalLoad'])) {
                    $mapping['changed_since'] = "-{$r['configuration']['incrementalLoad']} days";
                }
                if (empty($r['configuration']['export'])) {
                    $mapping['limit'] = 1;
                }
                $newConfig['configuration']['storage']['input']['tables'][] = $mapping;

                if (!empty($r['configuration']['grain'])) {
                    $r['configuration']['grain'] = explode(',', $r['configuration']['grain']);
                } else {
                    unset($r['configuration']['grain']);
                }

                unset($r['configuration']['export']);
                unset($r['configuration']['isExported']);
                unset($r['configuration']['incrementalLoad']);
                unset($r['configuration']['tableId']);
                unset($r['configuration']['ignoreFilter']);
                $newConfig['configuration']['parameters']['tables'][$r['id']] = $r['configuration'];
            }
            $newConfig['rows'] = [];
        }
        return $newConfig;
    }

    public function checkGoodDataConfiguration(array $config): void
    {
        if (!isset($config['configuration']['parameters']['user']['login'])) {
            throw new UserException("GoodData login is missing from configuration {$config['id']}");
        }
        if (!isset($config['configuration']['parameters']['user']['#password'])) {
            throw new UserException("GoodData password is missing from configuration {$config['id']}");
        }
        if (!isset($config['configuration']['parameters']['project']['pid'])) {
            throw new UserException("GoodData project pid is missing from configuration {$config['id']}");
        }
    }

    public function updateProductionLimit(int $limit): void
    {
        if ($limit > 0) {
            $this->manageApi->setProjectLimits(getenv('KBC_PROJECTID'), [
                [
                    "name" => "goodData.prodProjectsCount",
                    "value" => $limit,
                ],
            ]);
        }
    }

    public function getAddProjectToProvisioningParams(array $config, string $authToken): array
    {
        $params = [
            'pid' => $config['configuration']['parameters']['project']['pid'],
            'params' => [],
        ];
        if (in_array($authToken, ['demo', 'production'])) {
            $params['params']['keboolaToken'] = $authToken;
        } else {
            $params['params']['customToken'] = $authToken;
        }
        return $params;
    }

    public function addProjectToProvisioning(
        GoodDataProvisioningService $provisioning,
        array $newConfig
    ): void {
        $projectMeta = $this->getProjectMeta($newConfig);
        $authToken = $this->getAuthTokenFromProjectMeta($projectMeta);
        $provisioningParams = $this->getAddProjectToProvisioningParams($newConfig, $authToken);
        $provisioning->addProject($provisioningParams['pid'], $provisioningParams['params']);
    }

    public function addUsersToProvisioning(
        GoodDataProvisioningService $provisioning,
        LegacyGoodDataWriterService $writer,
        array $newConfig
    ): void {
        $login = $newConfig['configuration']['parameters']['user']['login'];
        // Add user to Provisioning only if it was created by Writer (conforms to the format used by it)
        if ($login === getenv('KBC_PROJECTID') . "-" . $newConfig['id']
            . '@' . $this->imageParameters['project_access_domain']) {
            $provisioning->addUser($login, ['uid' => $newConfig['configuration']['parameters']['user']['uid']]);
        }

        foreach ($writer->listUsers($newConfig['id']) as $user) {
            if ($user['email'] !== $newConfig['configuration']['parameters']['user']['login']) {
                $provisioning->addUser($user['email'], ['uid' => $user['uid']]);
            }
        }
    }

    public function getProjectMeta(array $config): array
    {
        try {
            $this->goodData->login(
                $config['configuration']['parameters']['project']['backendUrl']
                ?? $this->imageParameters['gooddata_url'],
                $config['configuration']['parameters']['user']['login'],
                $config['configuration']['parameters']['user']['#password']
            );
        } catch (\Keboola\GoodData\Exception $e) {
            throw new UserException("GoodData credentials of configuration {$config['id']} are not valid. ({$e->getMessage()})");
        }
        $pid = $config['configuration']['parameters']['project']['pid'];
        try {
            $result = $this->goodData->getProject($pid);
            if ($result['content']['state'] == 'DELETED') {
                throw new UserException("GoodData project $pid of configuration {$config['id']} is not accessible.");
            }
            return $result;
        } catch (\Keboola\GoodData\Exception $e) {
            throw new UserException("GoodData project $pid of configuration {$config['id']} is not accessible. ({$e->getMessage()})");
        }
    }

    public function getAuthTokenFromProjectMeta(array $projectMeta): string
    {
        if ($projectMeta['content']['authorizationToken'] == $this->imageParameters['#production_token']) {
            return 'production';
        }
        if ($projectMeta['content']['authorizationToken'] == $this->imageParameters['#demo_token']) {
            return 'demo';
        }
        return $projectMeta['content']['authorizationToken'];
    }
}

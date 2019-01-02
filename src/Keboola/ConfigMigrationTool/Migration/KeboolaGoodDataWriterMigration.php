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

    public function execute(): array
    {
        return $this->doExecute();
    }

    public function setProvisioning(GoodDataProvisioningService $provisioning) : void
    {
        $this->provisioning = $provisioning;
    }

    public function setLegacyWriter(LegacyGoodDataWriterService $legacyWriter) : void
    {
        $this->legacyWriter = $legacyWriter;
    }

    public function setGoodData(GoodDataService $goodData) : void
    {
        $this->goodData = $goodData;
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

                    $this->checkGoodDataConfiguration($newConfig);
                    $this->addProjectsToProvisioning($this->provisioning, $this->legacyWriter, $newConfig);
                    $this->addUsersToProvisioning($this->provisioning, $this->legacyWriter, $newConfig);

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
            }
        }
        if (!empty($oldConfig['rows'])) {
            $newConfig['configuration']['storage'] = ['input' => ['tables' => []]];
            foreach ($oldConfig['rows'] as $r) {
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
                }

                unset($r['configuration']['export']);
                unset($r['configuration']['isExported']);
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

    public function addProjectsToProvisioning(
        GoodDataProvisioningService $provisioning,
        LegacyGoodDataWriterService $writer,
        array $newConfig
    ): void {
        $projectMeta = $this->getProjectMeta($newConfig);
        $authToken = $this->getAuthTokenFromProjectMeta($projectMeta);
        $provisioningParams = $this->getAddProjectToProvisioningParams($newConfig, $authToken);
        $provisioning->addProject($provisioningParams['pid'], $provisioningParams['params']);

        foreach ($writer->listProjects($newConfig['id']) as $project) {
            if ($project['id'] !== $newConfig['configuration']['parameters']['project']['pid']) {
                $params = [];
                if ($project['authToken'] === 'keboola_production') {
                    $params['keboolaToken'] = 'production';
                } elseif ($project['authToken'] === 'keboola_demo') {
                    $params['keboolaToken'] = 'demo';
                } else {
                    $params['customToken'] = $project['authToken'];
                }
                $provisioning->addProject($project['id'], $params);
            }
        }
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
            return $this->goodData->getProject($pid);
        } catch (\Keboola\GoodData\Exception $e) {
            throw new UserException("GoodData project $pid of configuration {$config['id']} is not accessible. ({$e->getMessage()})");
        }
    }

    public function getAuthTokenFromProjectMeta(array $projectMeta): string
    {
        if ($projectMeta['content']['authorizationToken'] == $this->imageParameters['production_token']) {
            return 'production';
        }
        if ($projectMeta['content']['authorizationToken'] == $this->imageParameters['demo_token']) {
            return 'demo';
        }
        return $projectMeta['content']['authorizationToken'];
    }
}

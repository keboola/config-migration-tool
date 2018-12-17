<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Service\GoodDataProvisioningService;
use Keboola\ConfigMigrationTool\Service\LegacyGoodDataWriterService;
use Keboola\GoodData\Client as GDClient;
use Keboola\ConfigMigrationTool\Exception\ApplicationException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\StorageApi\ClientException;

class KeboolaGoodDataWriterMigration extends GenericCopyMigration
{
    /** @var GoodDataProvisioningService */
    protected $provisioningService;

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
        $provisioning = new GoodDataProvisioningService(
            $this->imageParameters['gooddata_provisioning_url'],
            $this->imageParameters['manage_token']
        );
        $writer = new LegacyGoodDataWriterService($this->imageParameters['gooddata_writer_url']);

        $createdConfigurations = [];
        foreach ($this->storageApiService->getConfigurations($this->originComponentId) as $oldConfig) {
            if (!isset($oldConfig['configuration']['migrationStatus'])
                || $oldConfig['configuration']['migrationStatus'] != 'success') {
                try {
                    $newConfig = $this->transformConfiguration($oldConfig);
                    $configuration = $this->buildConfigurationObject($this->destinationComponentId, $newConfig);

                    $this->checkGoodDataConfiguration($newConfig);
                    $this->addProjectsToProvisioning($provisioning, $writer, $newConfig);
                    $this->addUsersToProvisioning($provisioning, $writer, $newConfig);

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
            foreach ($oldConfig['rows'] as $r) {
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
        ];
        if (in_array($authToken, ['demo', 'production'])) {
            $params['keboolaToken'] = $authToken;
        } else {
            $params['customToken'] = $authToken;
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
        $provisioning->addProject($provisioningParams);

        foreach ($writer->listProjects($newConfig['id']) as $project) {
            if ($project['id'] !== $newConfig['configuration']['parameters']['project']['pid']) {
                $params = ['pid' => $project['id']];
                if ($project['authToken'] === 'keboola_production') {
                    $params['keboolaToken'] = 'production';
                } elseif ($project['authToken'] === 'keboola_demo') {
                    $params['keboolaToken'] = 'demo';
                } else {
                    $params['customToken'] = $project['authToken'];
                }
                $provisioning->addProject($params);
            }
        }
    }

    public function addUsersToProvisioning(
        GoodDataProvisioningService $provisioning,
        LegacyGoodDataWriterService $writer,
        array $newConfig
    ): void {
        $provisioning->addUser([
            'email' => $newConfig['configuration']['parameters']['user']['login'],
            'uid' => $newConfig['configuration']['parameters']['user']['uid'],
        ]);

        foreach ($writer->listUsers($newConfig['id']) as $user) {
            if ($user['email'] !== $newConfig['configuration']['parameters']['user']['login']) {
                $provisioning->addUser([
                    'email' => $user['email'],
                    'uid' => $user['uid'],
                ]);
            }
        }
    }

    public function getProjectMeta(array $config): array
    {
        $gd = new GDClient($config['configuration']['parameters']['project']['backendUrl']
            ?? $this->imageParameters['gooddata_url']);
        try {
            $gd->login(
                $config['configuration']['parameters']['user']['login'],
                $config['configuration']['parameters']['user']['#password']
            );
        } catch (\Keboola\GoodData\Exception $e) {
            throw new UserException("GoodData credentials of configuration {$config['id']} are not valid. ({$e->getMessage()})");
        }
        $pid = $config['configuration']['parameters']['project']['pid'];
        try {
            return $gd->getProjects()->getProject($pid)['project'];
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

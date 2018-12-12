<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use Keboola\ConfigMigrationTool\Service\OAuthService;
use Keboola\ConfigMigrationTool\Service\OAuthV3Service;
use Keboola\ConfigMigrationTool\Service\OrchestratorService;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Monolog\Logger;

class OAuthMigration extends DockerAppMigration
{
    /** @var array */
    private $config;

    /** @var OAuthService */
    private $oauthService;

    /** @var OAuthV3Service */
    private $oauthV3Service;

    public function __construct(array $config, Logger $logger)
    {
        parent::__construct($logger);
        $this->config = $config;

        $oauthV2Url = $this->storageApiService->getServiceUrl('syrup') . '/oauth-v2/';
        $oauthV3Url = getenv('OAUTH_API_URL') ?: $this->storageApiService->getServiceUrl(StorageApiService::OAUTH_SERVICE);

        $this->oauthService = new OAuthService($oauthV2Url);
        $this->oauthV3Service = new OAuthV3Service($oauthV3Url);
    }

    public function execute(): array
    {
        $componentId = $this->config['componentId'];
        $configurationId = $this->config['id'];

        // load configuration from SAPI
        $componentConfigurationJson = $this->storageApiService->getConfiguration($componentId, $configurationId);
        $componentConfiguration = $this->buildConfigurationObject($componentId, $componentConfigurationJson);

        // get Credentials from old OAuth Bundle
        $credentials = $this->oauthService->getCredentialsRaw($componentId, $configurationId);

        // add Credentials to new OAuth API
        $newCredentials = $this->getNewCredentialsFromOld($credentials);
        $response = $this->oauthV3Service->createCredentials($componentId, $newCredentials);

        // save configuration with version set to 3
        $this->saveConfigurationOptions($componentConfiguration, [
            'authorization' => [
                'oauth_api' =>[
                    'version' => 3,
                ],
            ],
        ]);

        return $response;
    }

    public function status() : array
    {
        $sapiService = new StorageApiService();
        $orchestratorService = new OrchestratorService();

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

    private function getNewCredentialsFromOld(\stdClass $credentials) : array
    {
        $newCredentials = [
            'id' => $credentials->id,
            'authorizedFor' => $credentials->authorized_for,
            'data' => $credentials->data,
        ];

        if (!empty($credentials->app_key)) {
            $newCredentials['appKey'] = $credentials->app_key;
        }

        if (!empty($credentials->app_secret_docker)) {
            $newCredentials['appSecretDocker'] = $credentials->app_secret_docker;
        }

        if (!empty($credentials->auth_url)) {
            $newCredentials['authUrl'] = $credentials->auth_url;
        }

        return $newCredentials;
    }
}

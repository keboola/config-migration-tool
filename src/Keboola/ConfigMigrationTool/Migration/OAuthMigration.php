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

    public function __construct($config, Logger $logger)
    {
        $this->config = $config;
        $this->oauthService = new OAuthService($config['region']);
        $this->oauthV3Service = new OAuthV3Service($config['region']);
        parent::__construct($logger);
    }

    public function execute(): array
    {
        $componentId = $this->config['componentId'];
        $configurationId = $this->config['id'];

        // load configuration from SAPI
        $componentConfigurationJson = $this->storageApiService->getConfiguration($componentId, $configurationId);
        $componentConfiguration = $this->buildConfigurationObject($componentId, $componentConfigurationJson);

        // get Credentials from old OAuth Bundle
        $credentials = $this->oauthService->getCredentials($componentId, $configurationId);

        var_dump($credentials);

        // add Credentials to new OAuth API
        $this->oauthV3Service->createCredentials($componentId, [
            'id' => $credentials->id,
            'email' => $credentials->authorizedFor,
            'data' => $credentials->{'#data'}
        ]);

        // set version to 3

        // save configuration
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
}

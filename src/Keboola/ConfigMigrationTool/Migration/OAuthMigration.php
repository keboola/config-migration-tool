<?php

declare(strict_types=1);

namespace Keboola\ConfigMigrationTool\Migration;

use GuzzleHttp\Exception\RequestException;
use Keboola\ConfigMigrationTool\Exception\UserException;
use Keboola\ConfigMigrationTool\Service\OAuthV3Service;
use Keboola\ConfigMigrationTool\Service\StorageApiService;
use Keboola\ConfigMigrationTool\Test\OAuthV2Service;
use Keboola\StorageApi\ClientException;
use Monolog\Logger;

class OAuthMigration extends DockerAppMigration
{
    /** @var array */
    private $config;

    /** @var OAuthV2Service */
    private $oauthService;

    /** @var OAuthV3Service */
    private $oauthV3Service;

    public function __construct(array $config, Logger $logger)
    {
        parent::__construct($logger);
        $this->config = $config;

        $oauthV2Url = $this->storageApiService->getServiceUrl(StorageApiService::SYRUP_SERVICE) . '/oauth-v2/';
        $oauthV3Url = getenv('OAUTH_API_URL') ?: $this->storageApiService->getServiceUrl(StorageApiService::OAUTH_SERVICE);

        $this->oauthService = new OAuthV2Service($oauthV2Url);
        $this->oauthV3Service = new OAuthV3Service($oauthV3Url);
    }

    public function execute(): array
    {
        $responses = [];
        foreach ($this->config['configurations'] as $configuration) {
            $componentId = $configuration['componentId'];
            $configurationId = $configuration['id'];

            // get Credentials from old OAuth Bundle
            try {
                $credentials = $this->oauthService->getCredentialsRaw($componentId, $configurationId);
            } catch (RequestException $e) {
                if ($e->getCode() === 400 && strstr($e->getMessage(), 'No data found for api') !== false) {
                    // component is not registered in OAuth API - skip
                    continue;
                }
                throw $e;
            }

            // add Credentials to new OAuth API
            $newCredentials = $this->getNewCredentialsFromOld($credentials);

            try {
                $response = $this->oauthV3Service->createCredentials($componentId, $newCredentials);
            } catch (RequestException $e) {
                if ($e->getCode() === 400 && strstr($e->getMessage(), 'already exists for component') !== false) {
                    // component credentials already exist - do nothing
                    $response = ['already exists'];
                } else {
                    throw $e;
                }
            }

            // load configuration from SAPI
            try {
                $componentConfigurationJson = $this->storageApiService->getConfiguration($componentId, $configurationId);
                $componentConfiguration = $this->buildConfigurationObject($componentId, $componentConfigurationJson);

                // save configuration with version set to 3
                $this->saveConfigurationOptions($componentConfiguration, [
                    'authorization' => [
                        'oauth_api' =>[
                            'version' => 3,
                        ],
                    ],
                ]);
            } catch (ClientException $e) {
                if (strstr($e->getMessage(), sprintf('Configuration %s not found', $configurationId)) !== false) {
                    // configurations was probably deleted while migrations was running
                    continue;
                }
                throw $e;
            }

            $responses[] = $response;
        }

        return $responses;
    }

    public function status() : array
    {
        throw new UserException('Not implemented');
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
